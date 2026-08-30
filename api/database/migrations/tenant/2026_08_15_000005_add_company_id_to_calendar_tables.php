<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #2623 — scoping tenant des calendriers : company_id ajouté à
 * calendar_connections et calendar_events (nullable pour les lignes
 * historiques, indexé). Idempotent (Render rejoue les migrations).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['calendar_connections', 'calendar_events'] as $table) {
            if (! schemaTableExists($table)) {
                continue;
            }

            if (! schemaHasColumn($table, 'company_id')) {
                Schema::table($table, function ($table): void {
                    $table->uuid('company_id')->nullable()->after('employee_id');
                    $table->index('company_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['calendar_connections', 'calendar_events'] as $table) {
            if (schemaTableExists($table) && schemaHasColumn($table, 'company_id')) {
                Schema::table($table, function ($table): void {
                    $table->dropIndex(['company_id']);
                    $table->dropColumn('company_id');
                });
            }
        }
    }
};
