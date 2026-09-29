-- 0. Activer l'extension pour la génération des UUID si nécessaire
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Création de la séquence pour la génération automatique du matricule
CREATE SEQUENCE IF NOT EXISTS agent_matricule_seq START 1001;

-- Fonction RPC pour générer le prochain matricule
CREATE OR REPLACE FUNCTION public.get_next_agent_matricule()
RETURNS text
LANGUAGE plpgsql
AS $$
BEGIN
  RETURN 'MAT-' || nextval('agent_matricule_seq')::text;
END;
$$;

-- 1. Table des Agents
CREATE TABLE IF NOT EXISTS public.agents (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    created_at timestamp with time zone DEFAULT now(),
    name text UNIQUE NOT NULL,
    matricule text DEFAULT 'MAT-' || nextval('agent_matricule_seq')::text,
    salaire_base numeric DEFAULT 0,
    is_active boolean DEFAULT true,
    date_entree date DEFAULT CURRENT_DATE,
    date_sortie date,
    service text,
    type_contrat text,
    statut_paiement text DEFAULT 'NON_PAYE'
);

-- 2. Table des Managers
CREATE TABLE IF NOT EXISTS public.managers (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    created_at timestamp with time zone DEFAULT now(),
    name text UNIQUE NOT NULL,
    matricule text DEFAULT 'MAT-' || nextval('agent_matricule_seq')::text,
    salaire_base numeric DEFAULT 0
);

-- 3. Table des Pointages
CREATE TABLE IF NOT EXISTS public.pointages (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    created_at timestamp with time zone DEFAULT now(),
    name text NOT NULL,
    date text NOT NULL,
    iso_date date NOT NULL,
    arrivee text,
    pause text,
    retour text,
    depart text,
    status text,
    total numeric DEFAULT 0,
    motif text,
    device_id text,
    ip_address text
);

-- Fonction pour vérifier qu'un appareil n'est pas utilisé par plusieurs personnes le même jour
CREATE OR REPLACE FUNCTION public.check_device_sharing()
RETURNS TRIGGER AS $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM public.pointages
    WHERE iso_date = NEW.iso_date
      AND device_id = NEW.device_id
      AND name != NEW.name
  ) THEN
    RAISE EXCEPTION 'Sécurité : Cet appareil est déjà utilisé par un autre agent aujourd''hui.';
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Déclencheur (Trigger) de sécurité
DROP TRIGGER IF EXISTS trigger_security_device_check ON public.pointages;
CREATE TRIGGER trigger_security_device_check
BEFORE INSERT ON public.pointages
FOR EACH ROW EXECUTE FUNCTION public.check_device_sharing();

-- 4. Table des Demandes de Congés
CREATE TABLE IF NOT EXISTS public.demandes_conges (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    created_at timestamp with time zone DEFAULT now(),
    agent_name text NOT NULL,
    type text NOT NULL,
    date_debut date NOT NULL,
    date_fin date NOT NULL,
    motif text,
    statut text DEFAULT 'EN ATTENTE' NOT NULL,
    acknowledged_at timestamp with time zone
);

-- 5. Table des Primes et Retenues (Gestion de la Paie)
CREATE TABLE IF NOT EXISTS public.primes_retenues (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    created_at timestamp with time zone DEFAULT now(),
    agent_name text NOT NULL,
    mois integer NOT NULL,
    annee integer NOT NULL,
    montant_prime numeric DEFAULT 0,
    montant_retenue numeric DEFAULT 0,
    CONSTRAINT unique_prime_retenue UNIQUE (agent_name, mois, annee)
);

-- 6. Table des Statistiques de Performance
CREATE TABLE IF NOT EXISTS public.agent_performance_stats (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    created_at timestamp with time zone DEFAULT now(),
    agent_name text NOT NULL,
    date date NOT NULL,
    dons integer DEFAULT 0 NOT NULL,
    refus_arg integer DEFAULT 0 NOT NULL,
    indecis integer DEFAULT 0 NOT NULL,
    del integer DEFAULT 0 NOT NULL,
    CONSTRAINT unique_agent_date UNIQUE (agent_name, date)
);

-- 7. Table des Commandes à distance pour les appareils
CREATE TABLE IF NOT EXISTS public.device_commands (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    created_at timestamp with time zone DEFAULT now(),
    device_id text NOT NULL,
    command text NOT NULL, -- ex: 'RESET_CACHE'
    executed boolean DEFAULT false
);

  -- 8. Configuration de l'annonce affichée au démarrage
  CREATE TABLE IF NOT EXISTS public.popup_config (
    id integer PRIMARY KEY CHECK (id = 1),
    title text NOT NULL DEFAULT 'Informations Importantes',
    content text,
    image_url text,
    is_active boolean NOT NULL DEFAULT false,
    updated_at timestamp with time zone NOT NULL DEFAULT now()
  );

-- Activation de la sécurité (RLS) et politiques par défaut
-- Note : Pour le développement, nous autorisons toutes les opérations. 
-- En production, restreignez ces accès aux utilisateurs authentifiés.

ALTER TABLE agents ENABLE ROW LEVEL SECURITY;
ALTER TABLE managers ENABLE ROW LEVEL SECURITY;
ALTER TABLE pointages ENABLE ROW LEVEL SECURITY;
ALTER TABLE demandes_conges ENABLE ROW LEVEL SECURITY;
ALTER TABLE primes_retenues ENABLE ROW LEVEL SECURITY;
ALTER TABLE agent_performance_stats ENABLE ROW LEVEL SECURITY;
ALTER TABLE device_commands ENABLE ROW LEVEL SECURITY;
ALTER TABLE popup_config ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow all" ON agents;
CREATE POLICY "Allow all" ON agents FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON managers;
CREATE POLICY "Allow all" ON managers FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON pointages;
DROP POLICY IF EXISTS "Public can read pointages" ON pointages;
DROP POLICY IF EXISTS "Public can insert pointages" ON pointages;
DROP POLICY IF EXISTS "Public can update pointages" ON pointages;
CREATE POLICY "Public can read pointages" ON pointages FOR SELECT USING (true);
CREATE POLICY "Public can insert pointages" ON pointages FOR INSERT WITH CHECK (true);
CREATE POLICY "Public can update pointages" ON pointages FOR UPDATE USING (true) WITH CHECK (true);
GRANT SELECT, INSERT, UPDATE ON TABLE public.pointages TO anon, authenticated;

DROP POLICY IF EXISTS "Allow all" ON demandes_conges;
CREATE POLICY "Allow all" ON demandes_conges FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON primes_retenues;
CREATE POLICY "Allow all" ON primes_retenues FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON agent_performance_stats;
CREATE POLICY "Allow all" ON agent_performance_stats FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON device_commands;
CREATE POLICY "Allow all" ON device_commands FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON popup_config;
CREATE POLICY "Allow all" ON popup_config FOR ALL USING (true) WITH CHECK (true);

-- Le frontend téléverse les images directement dans ce bucket.
INSERT INTO storage.buckets (id, name, public)
VALUES ('annonces', 'annonces', true)
ON CONFLICT (id) DO UPDATE SET public = true;

DROP POLICY IF EXISTS "Public can read announcement images" ON storage.objects;
CREATE POLICY "Public can read announcement images"
ON storage.objects FOR SELECT
USING (bucket_id = 'annonces');

DROP POLICY IF EXISTS "Public can upload announcement images" ON storage.objects;
CREATE POLICY "Public can upload announcement images"
ON storage.objects FOR INSERT
WITH CHECK (bucket_id = 'annonces');