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
    salaire_base numeric DEFAULT 0
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
    motif text
);

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

-- Activation de la sécurité (RLS) et politiques par défaut
-- Note : Pour le développement, nous autorisons toutes les opérations. 
-- En production, restreignez ces accès aux utilisateurs authentifiés.

ALTER TABLE agents ENABLE ROW LEVEL SECURITY;
ALTER TABLE managers ENABLE ROW LEVEL SECURITY;
ALTER TABLE pointages ENABLE ROW LEVEL SECURITY;
ALTER TABLE demandes_conges ENABLE ROW LEVEL SECURITY;
ALTER TABLE primes_retenues ENABLE ROW LEVEL SECURITY;
ALTER TABLE agent_performance_stats ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow all" ON agents;
CREATE POLICY "Allow all" ON agents FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON managers;
CREATE POLICY "Allow all" ON managers FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON pointages;
CREATE POLICY "Allow all" ON pointages FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON demandes_conges;
CREATE POLICY "Allow all" ON demandes_conges FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON primes_retenues;
CREATE POLICY "Allow all" ON primes_retenues FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow all" ON agent_performance_stats;
CREATE POLICY "Allow all" ON agent_performance_stats FOR ALL USING (true) WITH CHECK (true);