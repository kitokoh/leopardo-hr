<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS employees_company_id_email_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS employees_email_unique ON employees (email)');

            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique('employees_company_id_email_unique');
            $table->unique('email');
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS employees_email_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS employees_company_id_email_unique ON employees (company_id, email)');

            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropUnique('employees_email_unique');
            $table->unique(['company_id', 'email']);
        });
    }
};
