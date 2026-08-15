<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit expert 2026-08-15 (issue #2623) : calendar_connections stocke des
     * tokens OAuth (access/refresh) et calendar_events des données métier
     * sans `company_id` — défense en profondeur absente (le scope
     * BelongsToCompany et l'isolation par schéma ne suffisent pas seuls :
     * l'isolation primaire reste le search_path, mais la Constitution exige
     * `company_id` sur toute table tenant).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('calendar_connections', 'company_id')) {
            Schema::table('calendar_connections', function (Blueprint $table): void {
                $table->uuid('company_id')->nullable()->after('id');
                $table->index('company_id');
            });

            // Backfill depuis employees (même schéma tenant).
            DB::statement(
                'UPDATE calendar_connections SET company_id = e.company_id
                 FROM employees e
                 WHERE calendar_connections.employee_id = e.id
                   AND calendar_connections.company_id IS NULL
                   AND e.company_id IS NOT NULL'
            );

            Schema::table('calendar_connections', function (Blueprint $table): void {
                $table->uuid('company_id')->nullable(false)->change();
            });
        }

        if (! Schema::hasColumn('calendar_events', 'company_id')) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                $table->uuid('company_id')->nullable()->after('id');
                $table->index('company_id');
            });

            DB::statement(
                'UPDATE calendar_events SET company_id = e.company_id
                 FROM employees e
                 WHERE calendar_events.employee_id = e.id
                   AND calendar_events.company_id IS NULL
                   AND e.company_id IS NOT NULL'
            );

            Schema::table('calendar_events', function (Blueprint $table): void {
                $table->uuid('company_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropColumn('company_id');
        });

        Schema::table('calendar_connections', function (Blueprint $table): void {
            $table->dropColumn('company_id');
        });
    }
};
