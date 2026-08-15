<?php

declare(strict_types=1);

namespace Tests\Feature\DataModel;

use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3947 — index composites (company_id, employee_id) sur les tables
 * tenant chaudes (constitution IX). Les requêtes filtrent les deux colonnes ;
 * sans index composite, PostgreSQL revient à un scan mono-colonne.
 */
class CompositeTenantIndexesTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array<string, string>
     */
    public static function indexProvider(): array
    {
        return [
            'pay_slips' => ['pay_slips', 'pay_slips_company_employee_idx'],
            'attendance_logs' => ['attendance_logs', 'attendance_logs_company_employee_idx'],
            'absences' => ['absences', 'absences_company_employee_idx'],
            'salary_advances' => ['salary_advances', 'salary_advances_company_employee_idx'],
            'ledger_entries' => ['ledger_entries', 'ledger_entries_company_employee_idx'],
        ];
    }

    /**
     * @dataProvider indexProvider
     */
    public function test_composite_index_exists_on_hot_tenant_table(string $table, string $index): void
    {
        $exists = DB::table('information_schema.tables')
            ->where('table_schema', 'shared_tenants')
            ->where('table_name', $table)
            ->exists();

        if (! $exists) {
            $this->markTestSkipped("table {$table} absente du schéma tenant — migration non rejouée");

            return;
        }

        $found = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?',
            ['shared_tenants', $table, $index]
        );

        $this->assertNotNull($found, "index composite {$index} manquant sur {$table}");
    }
}
