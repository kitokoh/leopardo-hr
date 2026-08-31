<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant — issue #5967 (BC-05 WORKFORCE).
 *
 * absence_types.code : l'index UNIQUE est défini sur `code` SEUL alors que le
 * schéma est partagé (shared_tenants). Conséquence : SectorTemplateService::
 * seedAbsenceTypes() insère les codes standard (CA, MAL, MAT, PAT, CSS, INT,
 * CHOM) par tenant via insertOrIgnore() ; seul le 1er tenant les reçoit, tous
 * les suivants voient leurs inserts silencieusement ignorés (violation
 * d'unicité globale avalée par insertOrIgnore) → onboarding congés cassé.
 *
 * Fix : remplacer l'index unique global `absence_types_code_unique` par un index
 * unique composite `(company_id, code)`. Migration additive/idempotente,
 * rejouable (up/down), alignée sur le patron employees (#1613 / 000105).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('absence_types');

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop de l'index unique global (le nom par défaut Laravel est
            // <table>_<colonne>_unique). On tente aussi le nom de contrainte.
            DB::statement('ALTER TABLE '.$schema.'.absence_types DROP CONSTRAINT IF EXISTS absence_types_code_unique');
            DB::statement('DROP INDEX IF EXISTS '.$schema.'.absence_types_code_unique');
            DB::statement('DROP INDEX IF EXISTS absence_types_code_unique');
            // Création de l'index unique composite (company_id, code).
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS absence_types_company_id_code_unique ON '.$schema.'.absence_types (company_id, code)');

            return;
        }

        Schema::table("{$schema}.absence_types", function (Blueprint $table): void {
            $table->dropUnique('absence_types_code_unique');
            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('absence_types');

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS '.$schema.'.absence_types_company_id_code_unique');
            DB::statement('DROP INDEX IF EXISTS absence_types_company_id_code_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS absence_types_code_unique ON '.$schema.'.absence_types (code)');

            return;
        }

        Schema::table("{$schema}.absence_types", function (Blueprint $table): void {
            $table->dropUnique('absence_types_company_id_code_unique');
            $table->unique('code');
        });
    }
};
