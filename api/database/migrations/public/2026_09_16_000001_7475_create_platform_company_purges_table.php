<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #7475 — journal des purges/anonymisations de tenants (super-admin).
 *
 * Schéma **public**, qualifié explicitement, et **volontairement hors du tenant** :
 * la table `audit_logs` vit dans le schéma du tenant, donc une purge l'efface —
 * or le critère d'acceptation de #7475 exige que « l'opération est auditée et le
 * résultat est consultable » **après** la suppression. Cette table est la trace
 * qui survit : elle conserve l'identifiant et le nom de la société, le mode
 * (`purge` ou `anonymise`), l'auteur, l'horodatage et les volumes détruits.
 *
 * Idempotente (`CREATE TABLE IF NOT EXISTS`), comme `platform_ai_settings` :
 * Render rejoue des migrations dans un environnement où certaines tables existent.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS public.platform_company_purges (
    id bigserial PRIMARY KEY,
    company_id uuid NOT NULL,
    company_name varchar(150) NOT NULL,
    company_slug varchar(150) NULL,
    mode varchar(20) NOT NULL,
    status varchar(20) NOT NULL,
    requested_by varchar(180) NULL,
    requested_by_email varchar(180) NULL,
    reason text NULL,
    volumes jsonb NOT NULL DEFAULT '{}'::jsonb,
    error text NULL,
    created_at timestamp(0) with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS platform_company_purges_created_at_idx
    ON public.platform_company_purges (created_at DESC);
CREATE INDEX IF NOT EXISTS platform_company_purges_company_idx
    ON public.platform_company_purges (company_id);
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS public.platform_company_purges');
    }
};
