<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** PostgreSQL CREATE INDEX CONCURRENTLY cannot run inside a transaction. */
    public $withinTransaction = false;

    private const INDEX_NAME = 'idx_leave_balances_company_employee_year_type';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! schemaTableExists('leave_balances')) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            ['leave_balances', self::INDEX_NAME]
        );

        if ($exists === null) {
            DB::statement(sprintf(
                'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON leave_balances (company_id, employee_id, year, absence_type_id)',
                self::INDEX_NAME
            ));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.self::INDEX_NAME);
    }
};
