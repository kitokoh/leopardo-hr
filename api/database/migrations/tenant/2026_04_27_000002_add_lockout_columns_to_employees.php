<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('employees');

        Schema::table("{$schema}.employees", function (Blueprint $table) {
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestampTz('locked_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('employees');

        Schema::table("{$schema}.employees", function (Blueprint $table) {
            $table->dropColumn(['failed_login_attempts', 'locked_until']);
        });
    }
};
