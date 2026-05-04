<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_role_check');
            DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_role_check CHECK (role IN ('manager', 'employee', 'ordinary'))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_role_check');
            DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_role_check CHECK (role IN ('manager', 'employee'))");
        }
    }
};
