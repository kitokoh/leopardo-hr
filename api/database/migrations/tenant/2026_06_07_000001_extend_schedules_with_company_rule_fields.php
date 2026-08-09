<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('schedules');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.schedules", function (Blueprint $table): void {
            if (! schemaHasColumn('schedules', 'rest_days')) {
                $table->json('rest_days')->nullable()->after('work_days');
            }

            if (! schemaHasColumn('schedules', 'break_rules')) {
                $table->json('break_rules')->nullable()->after('break_minutes');
            }

            if (! schemaHasColumn('schedules', 'leave_rules')) {
                $table->json('leave_rules')->nullable()->after('rest_days');
            }

            if (! schemaHasColumn('schedules', 'assignment_notes')) {
                $table->text('assignment_notes')->nullable()->after('leave_rules');
            }

            if (! schemaHasColumn('schedules', 'updated_at')) {
                $table->timestampTz('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('schedules');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.schedules", function (Blueprint $table): void {
            foreach (['rest_days', 'break_rules', 'leave_rules', 'assignment_notes'] as $column) {
                if (schemaHasColumn('schedules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
