<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'recovery_email')) {
                $table->string('recovery_email', 150)->nullable()->after('personal_email');
            }

            if (! Schema::hasColumn('employees', 'personal_phone')) {
                $table->string('personal_phone', 30)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table): void {
            if (Schema::hasColumn('employees', 'personal_phone')) {
                $table->dropColumn('personal_phone');
            }

            if (Schema::hasColumn('employees', 'recovery_email')) {
                $table->dropColumn('recovery_email');
            }
        });
    }
};
