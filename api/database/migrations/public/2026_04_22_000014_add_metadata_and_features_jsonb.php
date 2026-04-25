<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * APV - Module boundaries foundation
 *
 * Ajoute:
 *  - companies.features JSONB : source de verite des modules actifs par company
 *    (active/desactive un module sans redeploiement, aligne sur APV L.08)
 *  - companies.metadata JSONB : extension sans migration (APV L.10)
 *  - user_invitations.metadata JSONB : idem
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
        DB::statement('SET search_path TO public');

        DB::statement('DROP INDEX IF EXISTS companies_features_gin');
        DB::statement('DROP INDEX IF EXISTS companies_metadata_gin');
        DB::statement('ALTER TABLE IF EXISTS public.companies DROP COLUMN IF EXISTS features');
        DB::statement('ALTER TABLE IF EXISTS public.companies DROP COLUMN IF EXISTS metadata');
        DB::statement('ALTER TABLE IF EXISTS public.user_invitations DROP COLUMN IF EXISTS metadata');
    }
};
