<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5540 — Onboarding personnel multi-statuts.
 *
 * Étend `company_requests` pour supporter les demandes d'intégration
 * (rejoindre une entreprise existante) en plus des demandes de création.
 *
 * - `type` : 'creation' (défaut historique) | 'integration'
 * - `target_company_id` : UUID de l'entreprise cible (pour type='integration')
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        Schema::table('company_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('company_requests', 'type')) {
                $table->string('type', 20)->default('creation')->after('id');
            }
            if (! Schema::hasColumn('company_requests', 'target_company_id')) {
                // UUID nullable — renseigné seulement pour type='integration'
                $table->uuid('target_company_id')->nullable()->after('type');
            }
        });

        // Backfill : les lignes existantes sont des demandes de création
        DB::table('company_requests')
            ->whereNull('type')
            ->orWhere('type', '')
            ->update(['type' => 'creation']);

        // sector : NOT NULL historique — non pertinent pour les demandes
        // d'intégration (rejoindre une entreprise existante, pas de secteur
        // à déclarer). Le flux de création le valide déjà nullable (#5540).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.company_requests ALTER COLUMN sector DROP NOT NULL');
        } else {
            DB::statement('ALTER TABLE company_requests MODIFY sector VARCHAR(100) NULL');
        }

        // Contrainte CHECK sur PostgreSQL pour limiter les valeurs acceptées.
        // NB : PostgreSQL ne supporte pas `ADD CONSTRAINT IF NOT EXISTS`
        // (syntaxe MySQL) → garde via pg_constraint (idempotent, CI sur PG16).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "DO \$\$
                 BEGIN
                   IF NOT EXISTS (
                     SELECT 1 FROM pg_constraint
                     WHERE conname = 'company_requests_type_check'
                   ) THEN
                     ALTER TABLE public.company_requests
                       ADD CONSTRAINT company_requests_type_check
                       CHECK (type IN ('creation', 'integration'));
                   END IF;
                 END \$\$;"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
            DB::statement(
                'ALTER TABLE public.company_requests
                 DROP CONSTRAINT IF EXISTS company_requests_type_check'
            );
        }

        Schema::table('company_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('company_requests', 'target_company_id')) {
                $table->dropColumn('target_company_id');
            }
            if (Schema::hasColumn('company_requests', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
