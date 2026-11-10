<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9098 (lot Racine) — verrouille la liste des rôles de
 * `employees_manager_role_check`.
 *
 * POURQUOI CE TEST EXISTE
 * -----------------------
 * La même panne s'est produite DEUX fois, à chaque fois de la même façon :
 * une nouvelle verticale ré-écrit la contrainte avec SA seule liste de rôles
 * au lieu de repartir de l'union.
 *
 *   - #6187 (restaurant) a écrasé les rôles Delivery de #6285 ;
 *   - #8004 a ré-unifié et documenté la règle « repartir de la liste
 *     complète, jamais de la sienne seule » ;
 *   - #6302 (caisse/stock) a de nouveau écrasé les rôles Delivery.
 *
 * Aucun test ne verrouillait la liste : la régression #6302 est passée en CI
 * sans être vue, et n'a été constatée qu'en cascade (SQLSTATE 23514 sur les
 * écritures Delivery, puis 25P02 dans les tests). Ce test ferme cette porte :
 * il échoue si un rôle acté disparaît de la contrainte, quel que soit le
 * fichier de migration responsable.
 *
 * PORTÉE — test uniquement. Aucune migration, aucun modèle, aucun contrat
 * d'API modifiés ici.
 */
class EmployeesManagerRoleCheckTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Rôles actés par au moins une verticale. Toute suppression est une
     * régression : un rôle retiré ici casse les écritures de la verticale
     * qui l'utilise.
     *
     * @var list<string>
     */
    private const REQUIRED_ROLES = [
        // Socle RH (création de la table).
        'principal',
        'rh',
        'dept',
        'comptable',
        'superviseur',
        // #marketing — 2026_07_16_000003.
        'marketing',
        // Manager transverse.
        'manager',
        // Delivery — #6285 (écrasés par #6187, restaurés par #8004, ré-écrasés
        // par #6302 : c'est la régression que ce test verrouille).
        'dispatcher',
        'delivery_manager',
        // Restaurant — #6187.
        'server',
        'kitchen',
        'rider',
        // Caisse / stock — #6302.
        'cashier',
        'stock',
    ];

    public function test_manager_role_check_accepts_every_acted_role(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Contrainte CHECK PostgreSQL uniquement.');
        }

        $schema = resolveTableSchema('employees');
        $this->assertNotNull($schema, 'Schéma employees introuvable.');

        $definition = $this->constraintDefinition($schema);
        $this->assertNotNull(
            $definition,
            'employees_manager_role_check absente : la contrainte a été supprimée sans être recréée.'
        );

        $missing = [];
        foreach (self::REQUIRED_ROLES as $role) {
            if (! str_contains($definition, "'{$role}'")) {
                $missing[] = $role;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Rôles absents de employees_manager_role_check : '.implode(', ', $missing).
            '. Une migration a ré-écrit la contrainte avec sa seule liste au lieu de '.
            "repartir de l'union (#8004) — toute écriture de ces rôles échoue en SQLSTATE 23514."
        );
    }

    /**
     * Preuve comportementale : PostgreSQL doit réellement ACCEPTER un rôle
     * Delivery. Une définition textuelle correcte mais une contrainte posée
     * sur un autre schéma, ou neutralisée, ne suffirait pas.
     *
     * On interroge la contrainte elle-même (`conbin`) plutôt que d'insérer une
     * ligne : un INSERT échouerait d'abord sur les colonnes NOT NULL, ce qui
     * rendrait le test vert pour la mauvaise raison.
     */
    public function test_manager_role_check_accepts_dispatcher_in_practice(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Contrainte CHECK PostgreSQL uniquement.');
        }

        $schema = resolveTableSchema('employees');
        $this->assertNotNull($schema, 'Schéma employees introuvable.');

        // `conbin` est l'arbre d'expression compilé de la contrainte : il
        // contient les littéraux réellement évalués par PostgreSQL, pas le
        // texte source de la migration.
        $row = DB::selectOne(
            'SELECT pg_get_expr(c.conbin, c.conrelid) AS expression
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_namespace n ON n.oid = t.relnamespace
              WHERE c.conname = ?
                AND t.relname = ?
                AND n.nspname = ?
                AND c.contype = ?',
            ['employees_manager_role_check', 'employees', $schema, 'c']
        );

        $this->assertNotNull(
            $row,
            'employees_manager_role_check absente ou neutralisée : la contrainte a été '.
            'supprimée sans être recréée.'
        );

        $expression = (string) $row->expression;

        foreach (['dispatcher', 'delivery_manager'] as $role) {
            $this->assertStringContainsString(
                "'{$role}'",
                $expression,
                "PostgreSQL n'évalue pas manager_role = '{$role}' : les rôles Delivery ont ".
                'de nouveau été retirés de employees_manager_role_check.'
            );
        }
    }

    private function constraintDefinition(string $schema): ?string
    {
        $row = DB::selectOne(
            'SELECT pg_get_constraintdef(c.oid) AS definition
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_namespace n ON n.oid = t.relnamespace
              WHERE c.conname = ?
                AND t.relname = ?
                AND n.nspname = ?',
            ['employees_manager_role_check', 'employees', $schema]
        );

        return $row?->definition;
    }
}
