<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration 0001 - Table publique : plans
 *
 * Schema : public
 * Dependances : aucune - premiere migration a executer
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.plans (
    id serial PRIMARY KEY,
    name varchar(50) NOT NULL,
    price_monthly decimal(10, 2) NOT NULL DEFAULT '0',
    price_yearly decimal(10, 2) NOT NULL DEFAULT '0',
    max_employees integer NULL,
    features jsonb NOT NULL DEFAULT '{}'::jsonb,
    trial_days smallint NOT NULL DEFAULT '14',
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS plans_name_unique ON public.plans (name);
COMMENT ON TABLE public.plans IS 'Plans tarifaires SaaS - Starter/Business/Enterprise';
COMMENT ON COLUMN public.plans.max_employees IS 'NULL = employes illimites (Enterprise)';
COMMENT ON COLUMN public.plans.features IS 'JSONB: {biometric, tasks, advanced_reports, excel_export, bank_export, billing_auto, multi_managers, photo_attendance, api_public, evaluations, schema_isolation}';
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS public.plans CASCADE');
    }
};
