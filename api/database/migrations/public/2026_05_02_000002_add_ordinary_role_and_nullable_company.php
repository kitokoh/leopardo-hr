<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update user_lookups in public schema
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        Schema::table('user_lookups', function (Blueprint $table) {
            $table->uuid('company_id')->nullable()->change();
            $table->string('schema_name', 63)->nullable()->change();
        });

        // 2. Update employees in tenant schema
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO shared_tenants,public');
        }

        if (Schema::hasTable('employees')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_role_check");
            }

            Schema::table('employees', function (Blueprint $table) {
                $table->uuid('company_id')->nullable()->change();
                $table->string('role', 20)->default('employee')->change();
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_role_check CHECK (role IN ('manager', 'employee', 'ordinary'))");
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }
    }

    public function down(): void {}
};
