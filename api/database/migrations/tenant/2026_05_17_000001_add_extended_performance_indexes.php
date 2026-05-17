<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $indexes = [
            ['contracts', 'idx_contracts_company_status', '(company_id, status)'],
            ['contracts', 'idx_contracts_end_date', '(end_date) WHERE status = \'active\''],
            ['contracts', 'idx_contracts_employee', '(employee_id, company_id)'],
            ['training_courses', 'idx_training_courses_company', '(company_id)'],
            ['training_sessions', 'idx_training_sessions_dates', '(start_date, end_date)'],
            ['training_enrollments', 'idx_training_enrollments_employee', '(employee_id, status)'],
            ['job_postings', 'idx_job_postings_company_status', '(company_id, status)'],
            ['applicants', 'idx_applicants_posting_status', '(job_posting_id, status)'],
            ['audit_logs', 'idx_audit_logs_company_created', '(company_id, created_at DESC)'],
            ['webhook_endpoints', 'idx_webhook_endpoints_company', '(company_id)'],
        ];

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
            'idx_contracts_company_status',
            'idx_contracts_end_date',
            'idx_contracts_employee',
            'idx_training_courses_company',
            'idx_training_sessions_dates',
            'idx_training_enrollments_employee',
            'idx_job_postings_company_status',
            'idx_applicants_posting_status',
            'idx_audit_logs_company_created',
            'idx_webhook_endpoints_company',
        ];

        foreach ($indexes as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
