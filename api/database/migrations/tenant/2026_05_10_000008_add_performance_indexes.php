<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

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
            if (! schemaTableExists($table)) {
                continue;
            }

            // #9671 : CONCURRENTLY est impossible dans une transaction ; le
            // helper retombe sur un CREATE INDEX classique en environnement de
            // test (sinon RefreshDatabase se bloque sur un verrou virtualxid).
            createIndexIfNotExists($name, $table, $columns);
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
