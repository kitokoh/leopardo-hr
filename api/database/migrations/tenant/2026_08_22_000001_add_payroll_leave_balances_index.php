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

        // #9671 : pas de CONCURRENTLY en environnement de test (transaction).
        createIndexIfNotExists(
            self::INDEX_NAME,
            'leave_balances',
            '(company_id, employee_id, year, absence_type_id)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // #9671 : DROP INDEX CONCURRENTLY est lui aussi interdit dans une
        // transaction — on l'évite en test pour ne pas bloquer le rollback.
        $concurrently = app()->runningUnitTests() || app()->environment('testing')
            ? ''
            : 'CONCURRENTLY ';

        DB::statement('DROP INDEX '.$concurrently.'IF EXISTS '.self::INDEX_NAME);
    }
};
