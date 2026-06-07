<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('schedules', 'rest_days')) {
                $table->json('rest_days')->nullable()->after('work_days');
            }

            if (! Schema::hasColumn('schedules', 'break_rules')) {
                $table->json('break_rules')->nullable()->after('break_minutes');
            }

            if (! Schema::hasColumn('schedules', 'leave_rules')) {
                $table->json('leave_rules')->nullable()->after('rest_days');
            }

            if (! Schema::hasColumn('schedules', 'assignment_notes')) {
                $table->text('assignment_notes')->nullable()->after('leave_rules');
            }

            if (! Schema::hasColumn('schedules', 'updated_at')) {
                $table->timestampTz('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedules')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table): void {
            foreach (['rest_days', 'break_rules', 'leave_rules', 'assignment_notes'] as $column) {
                if (Schema::hasColumn('schedules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
