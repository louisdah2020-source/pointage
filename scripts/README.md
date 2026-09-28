# Migration des données du projet vers Supabase

Ce dossier contient les scripts pour migrer les données historiques depuis MySQL vers Supabase.

## 1) Vérification rapide

```bash
php scripts/check_mysql_data.php
```

## 2) Migration des données du 01/09/2026 à aujourd'hui

Avant de lancer :

- vérifier que le schéma Supabase contient les tables attendues
- vérifier la clé publique Supabase configurée dans le front

Puis lancer :

```bash
php scripts/migrate_to_supabase.php
```

## 3) Remarques

- Le script cible les enregistrements depuis 2026-09-01.
- Les tables sont envoyées via l'API REST Supabase.
- Les données doublonnées sont gérées par upsert sur les clés métier.
- Les identifiants MySQL sont actuellement hardcodés dans le script pour un usage local de migration.
