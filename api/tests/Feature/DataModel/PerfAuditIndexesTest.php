<?php

declare(strict_types=1);

namespace Tests\Feature\DataModel;

use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5284 — perf/load : index manquants sur les tables tenant
 * volumineuses (audit des FK sans index + patterns de requêtes).
 *
 * Tables concernées : webhook_deliveries (retries), audit_logs (piste par
 * employé), leave_accruals (reporting par politique), approval_decisions
 * (chaîne d'approbation). Migration : 2026_08_23_000001_add_perf_audit_indexes.
 */
class PerfAuditIndexesTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function indexProvider(): array
    {
        return [
            'webhook_deliveries (endpoint, delivered_at)' => ['webhook_deliveries', 'webhook_deliveries_endpoint_delivered_idx'],
            'audit_logs (employee_id)' => ['audit_logs', 'audit_logs_employee_id_idx'],
            'leave_accruals (leave_policy_id)' => ['leave_accruals', 'leave_accruals_leave_policy_id_idx'],
            'approval_decisions (approval_request_id)' => ['approval_decisions', 'approval_decisions_approval_request_id_idx'],
        ];
    }

    /**
     * @dataProvider indexProvider
     */
    public function test_perf_index_exists_on_hot_tenant_table(string $table, string $index): void
    {
        $exists = DB::table('information_schema.tables')
            ->where('table_schema', 'shared_tenants')
            ->where('table_name', $table)
            ->exists();

        if (! $exists) {
            $this->markTestSkipped("table {$table} absente du schéma tenant — migration non rejouée");
        }

        $found = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?',
            ['shared_tenants', $table, $index]
        );

        $this->assertNotNull($found, "index perf {$index} manquant sur {$table}");
    }
}
