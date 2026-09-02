<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #3947 — règles constitution IX : index composite (company_id, employee_id)
 * sur les tables tenant chaudes. Les requêtes filtrent les deux colonnes
 * (PaySlipController, NotificationController, LedgerController…) mais seuls
 * des index mono-colonne existent : ajout des index composites additifs,
 * idempotents (rejouables sur Render).
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const INDEXES = [
        'pay_slips' => 'pay_slips_company_employee_idx',
        'attendance_logs' => 'attendance_logs_company_employee_idx',
        'absences' => 'absences_company_employee_idx',
        'salary_advances' => 'salary_advances_company_employee_idx',
        'ledger_entries' => 'ledger_entries_company_employee_idx',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $index) {
            if (! schemaTableExists($table)
                || ! schemaHasColumn($table, 'company_id')
                || ! schemaHasColumn($table, 'employee_id')) {
                continue;
            }

            DB::statement(
                "CREATE INDEX IF NOT EXISTS {$index} ON {$table} (company_id, employee_id)"
            );
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
