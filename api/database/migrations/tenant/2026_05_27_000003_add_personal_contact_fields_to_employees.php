<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('employees');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.employees", function (Blueprint $table): void {
            if (! schemaHasColumn('employees', 'recovery_email')) {
                $table->string('recovery_email', 150)->nullable()->after('personal_email');
            }

            if (! schemaHasColumn('employees', 'personal_phone')) {
                $table->string('personal_phone', 30)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('employees');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.employees", function (Blueprint $table): void {
            if (schemaHasColumn('employees', 'personal_phone')) {
                $table->dropColumn('personal_phone');
            }

            if (schemaHasColumn('employees', 'recovery_email')) {
                $table->dropColumn('recovery_email');
            }
        });
    }
};
