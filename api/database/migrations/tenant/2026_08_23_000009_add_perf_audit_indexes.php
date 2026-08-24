<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5284 — perf/load : index manquants sur les tables volumineuses
 * (audit index — payroll_runs, logs, documents).
 *
 * Audit des FK sans index (pg_constraint/pg_index) + patterns de requêtes
 * sur le jeu de benchmark DZ réaliste (PayrollBenchmarkSeeder, 500 employés) :
 *
 *  1. `webhook_deliveries (webhook_endpoint_id, delivered_at)` — la table
 *     grossit avec chaque tentative de webhook (retries) ; les requêtes
 *     « livraisons en attente d'un endpoint » (`delivered_at IS NULL`) et
 *     l'historique récent d'un endpoint seq-scannent sans cet index.
 *  2. `audit_logs (employee_id)` — FK ajoutée après création ; la piste
 *     d'audit par employé (RBAC, GDPR, workflow) seq-scanne à la lecture.
 *  3. `leave_accruals (leave_policy_id)` — reporting/audit des acquisitions
 *     par politique de congés (issue #5289) sans index dédié.
 *  4. `approval_decisions (approval_request_id)` — chaîne de décisions lue
 *     pour chaque demande d'approbation (workflow RH/pointage/paie).
 *
 * F-17 (#1595/#1933) : accès qualifiés par schéma résolu via
 * `resolveTableSchema` (convention #1613) — idempotent, rejouable sur Render.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->statements() as [$table, $index, $columns]) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue; // Table absente dans ce contexte (CI partielle).
            }

            DB::statement(sprintf(
                'CREATE INDEX IF NOT EXISTS %s ON %s (%s)',
                $index,
                $schema.'.'.$table,
                implode(', ', $columns)
            ));
        }
    }

    public function down(): void
    {
        foreach ($this->statements() as [$table, $index]) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue;
            }

            DB::statement(sprintf(
                'DROP INDEX IF EXISTS %s',
                $schema.'.'.$index
            ));
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: array<int, string>}>
     */
    private function statements(): array
    {
        return [
            ['webhook_deliveries', 'webhook_deliveries_endpoint_delivered_idx', ['webhook_endpoint_id', 'delivered_at']],
            ['audit_logs', 'audit_logs_employee_id_idx', ['employee_id']],
            ['leave_accruals', 'leave_accruals_leave_policy_id_idx', ['leave_policy_id']],
            ['approval_decisions', 'approval_decisions_approval_request_id_idx', ['approval_request_id']],
        ];
    }
};
