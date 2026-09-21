<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8004 — Ré-unifie le CHECK `employees_manager_role_check` : les migrations
 * 2026_08_30_000803 (rôles delivery #6285) et 2026_08_30_001533 (rôles
 * restaurant #6187) se sont écrasées mutuellement — chacune ré-écrivait la
 * contrainte avec sa seule liste. La restaurant, exécutée en dernière,
 * gagnait : `dispatcher` et `delivery_manager` étaient rejetés en SQLSTATE
 * 23514 à chaque écriture du module Delivery (cascades 25P02 en tests).
 *
 * La contrainte porte désormais l'UNION de tous les rôles actés :
 * socle RH (principal, rh, dept, comptable, superviseur, marketing) +
 * manager transverse + delivery (dispatcher, delivery_manager) +
 * restaurant (server, kitchen, rider).
 *
 * Idempotente (DROP IF EXISTS + ADD) et no-op hors PostgreSQL — même
 * pattern que les migrations précédentes de la série.
 */
return new class extends Migration
{
    /**
     * Liste canonique union — toute nouvelle verticale ajoutant un rôle
     * DOIT repartir de cette liste complète (jamais de la sienne seule).
     *
     * @var list<string>
     */
    private const MANAGER_ROLES = [
        'principal',
        'rh',
        'dept',
        'comptable',
        'superviseur',
        'marketing',
        'manager',
        'dispatcher',
        'delivery_manager',
        'server',
        'kitchen',
        'rider',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $schema = resolveTableSchema('employees');
        if ($schema === null) {
            return;
        }

        $roles = implode(', ', array_map(
            static fn (string $role): string => "'{$role}'",
            self::MANAGER_ROLES,
        ));

        DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_manager_role_check");
        DB::statement(
            "ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_manager_role_check ".
            "CHECK (manager_role IN ({$roles}))"
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

        // État antérieur = liste restaurant (#6187), la plus récente avant
        // cette union.
        DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_manager_role_check");
        DB::statement(
            "ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_manager_role_check ".
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing', 'manager', 'server', 'kitchen', 'rider'))"
        );
    }
};
