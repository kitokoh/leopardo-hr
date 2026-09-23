<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #7984 (audit 2026-09-20, point 2) — chaîne de paiement sans
 * contraintes FK : `payment_batches` / `payment_items` /
 * `payment_confirmations` sont indexées mais rien n'empêche des orphelins
 * financiers (item pointant un batch supprimé, confirmation pointant un
 * item disparu, etc.).
 *
 * Stratégie (PostgreSQL) : `ADD CONSTRAINT … NOT VALID` puis tentative de
 * `VALIDATE CONSTRAINT`.
 * - `NOT VALID` protège immédiatement toutes les NOUVELLES écritures sans
 *   exiger que l'existant soit propre (pas de verrou long, pas d'échec de
 *   déploiement sur un tenant porteur d'orphelins historiques).
 * - La validation est ensuite tentée ; si elle échoue (orphelins présents),
 *   la contrainte reste NOT VALID et l'orphelin est journalisé — nettoyage
 *   manuel puis `ALTER TABLE … VALIDATE CONSTRAINT …` (documenté dans
 *   l'issue #7984). On ne supprime JAMAIS de lignes financières d'office.
 * - `ON DELETE`/`ON UPDATE` restent en NO ACTION (défaut) : aucune cascade
 *   destructive sur des enregistrements financiers.
 *
 * `company_id` (uuid, table `public.companies`) est volontairement hors
 * périmètre : en mode schema-per-tenant la référence cross-schema n'est pas
 * portable (#8055/#8056 en cours de tranchage).
 */
return new class extends Migration
{
    /** @var array<int, array{table: string, column: string, references: string, name: string}> */
    private const FOREIGN_KEYS = [
        ['table' => 'payment_batches', 'column' => 'payroll_run_id', 'references' => 'payroll_runs', 'name' => 'payment_batches_payroll_run_id_foreign'],
        ['table' => 'payment_items', 'column' => 'payment_batch_id', 'references' => 'payment_batches', 'name' => 'payment_items_payment_batch_id_foreign'],
        ['table' => 'payment_items', 'column' => 'pay_slip_id', 'references' => 'pay_slips', 'name' => 'payment_items_pay_slip_id_foreign'],
        ['table' => 'payment_items', 'column' => 'employee_id', 'references' => 'employees', 'name' => 'payment_items_employee_id_foreign'],
        ['table' => 'payment_items', 'column' => 'salary_advance_id', 'references' => 'salary_advances', 'name' => 'payment_items_salary_advance_id_foreign'],
        ['table' => 'payment_confirmations', 'column' => 'payment_batch_id', 'references' => 'payment_batches', 'name' => 'payment_confirmations_payment_batch_id_foreign'],
        ['table' => 'payment_confirmations', 'column' => 'payment_item_id', 'references' => 'payment_items', 'name' => 'payment_confirmations_payment_item_id_foreign'],
        ['table' => 'payment_confirmations', 'column' => 'employee_id', 'references' => 'employees', 'name' => 'payment_confirmations_employee_id_foreign'],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEYS as $fk) {
            if (! schemaTableExists($fk['table']) || ! schemaTableExists($fk['references'])) {
                continue;
            }

            if (DB::getDriverName() === 'pgsql') {
                $exists = DB::selectOne(
                    'SELECT 1 FROM pg_constraint WHERE conname = ? AND connamespace = current_schema()::regnamespace',
                    [$fk['name']]
                );

                if ($exists !== null) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (id) NOT VALID',
                    $fk['table'],
                    $fk['name'],
                    $fk['column'],
                    $fk['references']
                ));

                try {
                    DB::statement(sprintf('ALTER TABLE %s VALIDATE CONSTRAINT %s', $fk['table'], $fk['name']));
                } catch (\Illuminate\Database\QueryException $e) {
                    // Orphelins historiques : la contrainte reste NOT VALID
                    // (les nouvelles écritures sont déjà protégées).
                    // Remédiation : nettoyer puis VALIDATE — voir issue #7984.
                    logger()->warning('FK #7984 laissée NOT VALID (orphelins à nettoyer)', [
                        'constraint' => $fk['name'],
                        'table' => $fk['table'],
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                Schema::table($fk['table'], function ($table) use ($fk): void {
                    $table->foreign($fk['column'], $fk['name'])->references('id')->on($fk['references']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::FOREIGN_KEYS) as $fk) {
            if (! schemaTableExists($fk['table'])) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $fk['table'], $fk['name']));
        }
    }
};
