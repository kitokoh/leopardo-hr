<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-d (audit FuelStation 2026-10-16) — conservation de l'historique
 * financier et opérationnel lors d'une suppression.
 *
 * CONSTAT
 * Les migrations FUEL-002/003/004/007 déclaraient des clés étrangères
 * `cascadeOnDelete()` vers `employees` et `fuel_cash_sessions`. Or
 * `App\Core\Auth\Domain\Models\Employee` n'utilise PAS `SoftDeletes` : la
 * suppression d'un employé est un DELETE physique. Conséquence mesurée en
 * base (PostgreSQL) :
 *
 *   fuel_sales                   | fuel_sales_employee_fk            | ... ON DELETE CASCADE
 *   fuel_sales                   | fuel_sales_cash_session_fk        | ... ON DELETE CASCADE
 *   fuel_cash_sessions           | fuel_cash_sessions_opened_by_fk   | ... ON DELETE CASCADE
 *   fuel_shift_assignments       | fuel_shift_assignments_employee_fk| ... ON DELETE CASCADE
 *   fuel_cash_session_movements  | fuel_cash_session_movements_session_fk | ... ON DELETE CASCADE
 *
 * → supprimer un employé, ou purger une session de caisse, EFFAÇAIT
 *   silencieusement le registre des ventes, les affectations de poste et les
 *   mouvements de caisse qui y étaient rattachés. Une correction (suppression)
 *   effaçait donc l'historique financier, ce que le domaine « Caisse et
 *   règlements » et le §6 (cohérence et traçabilité) interdisent.
 *
 * CORRECTIF (politique retenue)
 * 1. `fuel_sales.cash_session_id` est NULLABLE → `ON DELETE SET NULL` : la
 *    vente SURVIT, seul son rattachement à la session est rompu. C'est le
 *    comportement attendu d'un registre de ventes (la vente a bien eu lieu,
 *    même si la session qui la rattachait est purgée).
 * 2. Les colonnes NON NULLABLES (`fuel_sales.employee_id`,
 *    `fuel_cash_sessions.opened_by`, `fuel_shift_assignments.employee_id`,
 *    `fuel_cash_session_movements.session_id`) passent en
 *    `ON DELETE RESTRICT` : la suppression est REFUSÉE tant que des lignes
 *    d'historique y renvoient. On ne peut pas mettre ces colonnes à NULL sans
 *    casser l'invariant « toute vente a un opérateur identifié », et
 *    blanchir l'identité de l'opérateur sur une pièce financière serait une
 *    perte de traçabilité en soi. Échouer franchement vaut mieux qu'effacer en
 *    silence : l'archivage/anonimisation de `Employee` est une décision
 *    produit transversale (aujourd'hui impossible : pas de `SoftDeletes`).
 *
 * Migration idempotente : recréation des contraintes par leur nom, avec
 * garde sur l'existence de la table et du schéma. `down()` restaure le
 * comportement CASCADE d'origine.
 */
return new class extends Migration
{
    /**
     * Contraintes à reposer, avec leur table, leur clé, leur cible et
     * l'action de suppression voulue.
     *
     * @var list<array{table:string,constraint:string,columns:string,foreign:string,references:string,onDelete:string}>
     */
    private const PRESERVE = [
        [
            'table' => 'fuel_sales',
            'constraint' => 'fuel_sales_cash_session_fk',
            'columns' => 'cash_session_id',
            'foreign' => 'fuel_cash_sessions',
            'references' => 'id',
            'onDelete' => 'SET NULL',
        ],
        [
            'table' => 'fuel_sales',
            'constraint' => 'fuel_sales_employee_fk',
            'columns' => 'employee_id',
            'foreign' => 'employees',
            'references' => 'id',
            'onDelete' => 'RESTRICT',
        ],
        [
            'table' => 'fuel_cash_sessions',
            'constraint' => 'fuel_cash_sessions_opened_by_fk',
            'columns' => 'opened_by',
            'foreign' => 'employees',
            'references' => 'id',
            'onDelete' => 'RESTRICT',
        ],
        [
            'table' => 'fuel_shift_assignments',
            'constraint' => 'fuel_shift_assignments_employee_fk',
            'columns' => 'employee_id',
            'foreign' => 'employees',
            'references' => 'id',
            'onDelete' => 'RESTRICT',
        ],
        [
            'table' => 'fuel_cash_session_movements',
            'constraint' => 'fuel_cash_session_movements_session_fk',
            'columns' => 'session_id',
            'foreign' => 'fuel_cash_sessions',
            'references' => 'id',
            'onDelete' => 'RESTRICT',
        ],
    ];

    public function up(): void
    {
        foreach (self::PRESERVE as $spec) {
            $this->recreate($spec, $spec['onDelete']);
        }
    }

    public function down(): void
    {
        // Restaure le comportement d'origine (cascade) pour les contraintes
        // posées par cette migration. `fuel_sales_employee_fk` était
        // CASCADE ; on rétablit l'état antérieur à l'identique.
        foreach (self::PRESERVE as $spec) {
            $this->recreate($spec, 'CASCADE');
        }
    }

    /**
     * @param  array{table:string,constraint:string,columns:string,foreign:string,references:string,onDelete:string}  $spec
     */
    private function recreate(array $spec, string $onDelete): void
    {
        if (! Schema::hasTable($spec['table'])) {
            return;
        }

        if (! Schema::hasTable($spec['foreign'])) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
            $this->quote($spec['table']),
            $this->quote($spec['constraint'])
        ));

        DB::statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s',
            $this->quote($spec['table']),
            $this->quote($spec['constraint']),
            $this->quote($spec['columns']),
            $this->quote($spec['foreign']),
            $this->quote($spec['references']),
            $onDelete
        ));
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
