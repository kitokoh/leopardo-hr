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

        // Contrainte CHECK sur PostgreSQL pour limiter les valeurs acceptées
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE public.company_requests
                 ADD CONSTRAINT IF NOT EXISTS company_requests_type_check
                 CHECK (type IN ('creation', 'integration'))"
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
