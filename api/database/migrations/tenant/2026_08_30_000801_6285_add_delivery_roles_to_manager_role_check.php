<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BC-26 DELIVERY — rôles managériaux du module Delivery (DELIVERY-201/#6285,
 * matrice RBAC BC-26-D05/#6294).
 *
 * Le module Delivery distingue quatre personas métier (spec
 * SOLUTION_DELIVERY.md §4) : `delivery.admin` (paramétrage → principal),
 * `delivery.dispatcher` (gestion livraisons/tournées), `delivery.manager`
 * (rapports/COD) et `delivery.rider` (mobile livreur — employé, pas manager).
 *
 * Deux nouvelles valeurs de `employees.manager_role` sont nécessaires :
 *   - `dispatcher`      — préparateur des tournées (CRUD livraisons, assign,
 *                         close) ;
 *   - `delivery_manager`— pilotage (rapports, réconciliation COD).
 *
 * Précisément le pattern de la migration Marketing
 * (2026_07_16_000003_add_marketing_to_manager_role_check_constraint.php,
 * #4468) : PostgreSQL génère une colonne VARCHAR + contrainte CHECK pour
 * Schema::enum() — il faut recréer la contrainte pour autoriser les nouvelles
 * valeurs. No-op sur les autres drivers.
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
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing', 'manager', 'dispatcher', 'delivery_manager'))"
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
