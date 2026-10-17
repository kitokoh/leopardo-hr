<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REST-OS-4 (Mission Leopardo Restaurant OS) — Étend la contrainte
 * employees_manager_role_check aux rôles caissier et responsable de stock.
 *
 * Le RBAC de la plateforme est porté par `employees.manager_role` (CHECK
 * généré par Schema::enum()). La matrice `restaurant.*` mappe les personas de
 * la spec : caissier (`restaurant.cashier` → 'cashier'), responsable de stock
 * (`restaurant.stock` → 'stock'). Sans cette extension, toute écriture de ces
 * rôles échouerait en SQLSTATE 23514 — même précédent que 'manager'/'server'/
 * 'kitchen'/'rider' (2026_08_30_001533_6187).
 *
 * No-op sur les drivers non-PostgreSQL. Schéma résolu via resolveTableSchema().
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
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing', 'manager', 'server', 'kitchen', 'rider', 'cashier', 'stock'))"
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
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing', 'manager', 'server', 'kitchen', 'rider'))"
        );
    }
};
