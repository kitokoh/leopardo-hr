<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('employees');

        if ($schema === null || ! schemaTableExists('employees')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_role_check");
            DB::statement("ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_role_check CHECK (role IN ('manager', 'employee', 'ordinary'))");
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('employees');

        if ($schema === null || ! schemaTableExists('employees')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"employees\" DROP CONSTRAINT IF EXISTS employees_role_check");
            DB::statement("ALTER TABLE \"{$schema}\".\"employees\" ADD CONSTRAINT employees_role_check CHECK (role IN ('manager', 'employee'))");
        }
    }
};
