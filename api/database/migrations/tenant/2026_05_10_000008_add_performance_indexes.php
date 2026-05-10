<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        $indexes = [
            ['employees', 'idx_employees_company_status', '(company_id, status)'],
            ['employees', 'idx_employees_manager_id', '(manager_id)'],
            ['absences', 'idx_absences_employee_dates', '(employee_id, start_date, end_date)'],
            ['absences', 'idx_absences_status_company', '(company_id, status)'],
            ['attendance_logs', 'idx_attendance_company_date', '(company_id, date)'],
            ['payrolls', 'idx_payrolls_period', '(company_id, period_year, period_month)'],
        ];

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($indexes as [$table, $name, $columns]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $exists = DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $name]
            );

            if (! $exists) {
                DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} ON {$table} {$columns}");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $indexes = [
            'idx_employees_company_status',
            'idx_employees_manager_id',
            'idx_absences_employee_dates',
            'idx_absences_status_company',
            'idx_attendance_company_date',
            'idx_payrolls_period',
        ];

        foreach ($indexes as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
