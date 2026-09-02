<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6187 (RESTO-306) — Étend le CHECK employees_manager_role_check aux rôles
 * restaurant (manager de salle, serveur, cuisinier, livreur).
 *
 * Le RBAC de la plateforme est porté par `employees.manager_role`
 * (VARCHAR + contrainte CHECK générée par Schema::enum() — voir
 * 2026_04_01_000101_create_employees_table.php). La matrice des permissions
 * `restaurant.*` (docs/architecture/RBAC_RESTAURANT_MATRIX.md, RESTO-306)
 * mappe les personas de la spec (§1.2) sur ces rôles : manager de salle
 * (`restaurant.manager` → 'manager'), serveur (`restaurant.server` →
 * 'server'), cuisinier (`restaurant.kitchen` → 'kitchen'), livreur
 * (`restaurant.rider` → 'rider').
 *
 * Sans cette extension, toute écriture `manager_role='manager'` échouait en
 * SQLSTATE 23514 (check violation) — même précédent que 'marketing'
 * (2026_07_16_000003, PR #856). No-op sur les drivers non-PostgreSQL.
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
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing', 'manager', 'server', 'kitchen', 'rider'))"
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
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing'))"
        );
    }
};
