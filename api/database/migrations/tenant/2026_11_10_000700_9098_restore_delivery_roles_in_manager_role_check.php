<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #9098 (lot Racine) — `employees_manager_role_check` a de nouveau perdu les
 * rôles Delivery : `dispatcher` et `delivery_manager` sont rejetés en SQLSTATE
 * 23514, ce qui casse toute écriture du module Delivery et cascade en 25P02
 * dans les tests.
 *
 * HISTORIQUE — la même panne s'est déjà produite deux fois :
 *
 *   1. 2026_08_30_000803 (#6285) ajoute `dispatcher` + `delivery_manager`.
 *   2. 2026_08_30_001533 (#6187) ré-écrit la contrainte avec SA seule liste
 *      (rôles restaurant) : les rôles Delivery disparaissent.
 *   3. 2026_09_21_000003 (#8004) ré-unifie la contrainte sur l'UNION des
 *      rôles et documente explicitement la règle : « toute nouvelle verticale
 *      ajoutant un rôle DOIT repartir de cette liste complète (jamais de la
 *      sienne seule) ».
 *   4. 2026_10_16_140002 (#6302) ré-écrit la contrainte avec SA seule liste
 *      (rôles restaurant + `cashier` + `stock`) : `dispatcher` et
 *      `delivery_manager` disparaissent de nouveau. C'est la régression que
 *      cette migration corrige.
 *
 * La cause n'est donc pas une liste fausse mais une liste DUPLIQUÉE : chaque
 * verticale recopie la contrainte au lieu de la dériver d'une source unique.
 * Tant que ce pattern subsiste, la panne reviendra à la prochaine verticale.
 *
 * PORTÉE — cette migration ne fait que restaurer l'UNION attendue (rôles
 * Delivery + rôles restaurant + `cashier`/`stock` de #6302). Elle ne modifie
 * aucune autre migration : réécrire `2026_10_16_140002` serait un changement
 * d'historique, interdit sur une base déjà migrée.
 *
 * Idempotente (DROP IF EXISTS + ADD) et no-op hors PostgreSQL — même pattern
 * que les migrations précédentes de la série.
 */
return new class extends Migration
{
    /**
     * Liste canonique union — socle RH + manager transverse + delivery
     * (#6285) + restaurant (#6187) + caisse/stock (#6302).
     *
     * Toute nouvelle verticale ajoutant un rôle DOIT repartir de cette liste
     * complète, jamais de la sienne seule (#8004).
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
        'cashier',
        'stock',
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

        // État antérieur = liste de #6302 (restaurant + cashier + stock), la
        // plus récente avant cette restauration.
        //
        // CAVEAT (perte de données, même classe que #8023) : ce `down()`
        // RÉ-IMPOSE une liste qui REJETTE `dispatcher` et `delivery_manager`.
        // Toute ligne `employees` écrite ENTRE `up()` et `down()` avec l'une
        // de ces deux valeurs viole la contrainte au moment du ADD CONSTRAINT :
        // PostgreSQL lève SQLSTATE 23514 et le rollback ÉCHOUE, la contrainte
        // restant absente (le DROP a déjà eu lieu) — l'état est PIRE qu'avant.
        //
        // Remédiation AVANT tout `migrate:rollback` de cette migration :
        // `UPDATE employees SET manager_role = NULL WHERE manager_role IN
        // ('dispatcher', 'delivery_manager')`, puis vérifier
        // `SELECT DISTINCT manager_role FROM employees`. Le rollback n'est
        // légitime que si le module Delivery n'a jamais écrit ces rôles —
        // sinon, ne pas revenir en arrière : cette union est précisément le
        // correctif du CHECK écrasé.
        DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_manager_role_check");
        DB::statement(
            "ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_manager_role_check ".
            "CHECK (manager_role IN ('principal', 'rh', 'dept', 'comptable', 'superviseur', 'marketing', 'manager', 'server', 'kitchen', 'rider', 'cashier', 'stock'))"
        );
    }
};
