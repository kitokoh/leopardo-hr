<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marketing — correction Phase 0 (bug latent).
 *
 * La migration 2026_06_22_000001_add_marketing_to_manager_role_enum.php
 * documentait "pas de changement DDL necessaire" en affirmant que la
 * colonne manager_role est un simple VARCHAR nullable. C'est inexact sur
 * PostgreSQL : Schema::enum() genere en realite une colonne VARCHAR
 * accompagnee d'une contrainte CHECK enumerant les valeurs autorisees
 * (voir 2026_04_01_000101_create_employees_table.php). Cette contrainte
 * n'a jamais ete mise a jour pour inclure 'marketing', donc meme apres le
 * fix de validation Laravel (Phase 0, PR #856), toute tentative d'INSERT
 * ou UPDATE avec manager_role='marketing' echoue au niveau base avec
 * "employees_manager_role_check" violation — reproduit par les tests
 * Feature du module Marketing (Phase 2).
 *
 * Cette migration recree la contrainte CHECK pour autoriser 'marketing'
 * en plus des valeurs existantes. No-op sur les drivers non-PostgreSQL
 * (MySQL/SQLite n'ont pas cette contrainte generee par Schema::enum()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $schema = resolveTableSchema('employees');
        if ($schema === null) {
            return;
        }

        DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_manager_role_check");
        DB::statement(
            "ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_manager_role_check ".
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing'))"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $schema = resolveTableSchema('employees');
        if ($schema === null) {
            return;
        }

        DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_manager_role_check");
        DB::statement(
            "ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_manager_role_check ".
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur'))"
        );
    }
};
