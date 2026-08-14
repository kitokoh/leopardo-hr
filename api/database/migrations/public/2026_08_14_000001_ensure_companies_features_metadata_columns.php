<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #1772 — Réparation d'une dérive post backup/restore.
 *
 * Contexte : la migration `2026_04_22_000014_add_metadata_and_features_jsonb`
 * est enregistrée comme exécutée dans `public.migrations` en production, mais
 * l'ALTER réel a été perdu (restore partiel) : la table `public.companies` n'a
 * ni `features` ni `metadata`. `migrate` ne rejoue jamais une migration
 * enregistrée → GET /employees/{id} → 500 (eager load `company:...,features`)
 * et feature flags silencieusement désactivés (Company::hasFeature()).
 *
 * Ce fichier est une migration NOUVELLE (jamais enregistrée) : elle est donc
 * rejouée au prochain `php artisan migrate` sur TOUT environnement, y compris
 * ceux qui ont déjà la colonne (no-op grâce à `ADD COLUMN IF NOT EXISTS`).
 *
 * Idempotente : peut être exécutée plusieurs fois sans effet de bord.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'companies'
    ) THEN
        ALTER TABLE public.companies
            ADD COLUMN IF NOT EXISTS features jsonb NOT NULL DEFAULT '{}'::jsonb;

        ALTER TABLE public.companies
            ADD COLUMN IF NOT EXISTS metadata jsonb NOT NULL DEFAULT '{}'::jsonb;

        COMMENT ON COLUMN public.companies.features IS 'Feature flags par module (APV L.08). Ex: {"rh":true,"finance":false,"cameras":false}. Toggle par super-admin, sans redeploiement.';
        COMMENT ON COLUMN public.companies.metadata IS 'Champs d extension JSONB (APV L.10). Aucune donnee critique ici.';

        CREATE INDEX IF NOT EXISTS companies_features_gin ON public.companies USING GIN (features);
        CREATE INDEX IF NOT EXISTS companies_metadata_gin ON public.companies USING GIN (metadata);
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'user_invitations'
    ) THEN
        ALTER TABLE public.user_invitations
            ADD COLUMN IF NOT EXISTS metadata jsonb NOT NULL DEFAULT '{}'::jsonb;
    END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        // Volontairement vide : cette migration ne fait que réparer un état
        // attendu du schéma (colonne existante en théorie). Un down() qui
        // supprimerait les colonnes serait destructeur pour les données.
    }
};
