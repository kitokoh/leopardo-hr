<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $schema = resolveTableSchema('notification_preferences');
        if ($schema === null || ! schemaTableExists('notification_preferences')) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint c
        JOIN pg_class t ON t.oid = c.conrelid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE n.nspname = '{$schema}'
          AND t.relname = 'notification_preferences'
          AND c.conname = 'notification_preferences_company_employee_unique'
    ) THEN
        ALTER TABLE "{$schema}"."notification_preferences"
            ADD CONSTRAINT notification_preferences_company_employee_unique
            UNIQUE (company_id, employee_id);
    END IF;
END \$\$;
SQL);
    }

    public function down(): void
    {
        $schema = resolveTableSchema('notification_preferences');
        if ($schema === null || ! schemaTableExists('notification_preferences') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE \"{$schema}\".\"notification_preferences\" DROP CONSTRAINT IF EXISTS notification_preferences_company_employee_unique");
    }
};