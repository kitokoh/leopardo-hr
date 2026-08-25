<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5324 — workflow de départ : nouveau statut employé `departed`.
 *
 * Précédent : 2026_05_03_000001_add_ordinary_role_to_employees.php (même
 * pattern DROP + ADD CONSTRAINT qualifié par schéma). `status` est un
 * VARCHAR + CHECK (Laravel enum) — pas un enum PG natif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('employees');

        if ($schema === null || ! schemaTableExists('employees')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_status_check");
            DB::statement("ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_status_check CHECK (status IN ('active', 'suspended', 'archived', 'departed'))");
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('employees');

        if ($schema === null || ! schemaTableExists('employees')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_status_check");
            DB::statement("ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_status_check CHECK (status IN ('active', 'suspended', 'archived'))");
        }
    }
};
