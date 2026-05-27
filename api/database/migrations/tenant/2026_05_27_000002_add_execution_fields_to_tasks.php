<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('tasks', 'estimated_minutes')) {
                $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('tasks', 'completed_minutes')) {
                $table->unsignedSmallInteger('completed_minutes')->nullable()->after('estimated_minutes');
            }

            if (! Schema::hasColumn('tasks', 'completed_at')) {
                $table->timestampTz('completed_at')->nullable()->after('completed_minutes');
            }

            if (! Schema::hasColumn('tasks', 'completion_note')) {
                $table->text('completion_note')->nullable()->after('completed_at');
            }

            if (! Schema::hasColumn('tasks', 'performance_score')) {
                $table->decimal('performance_score', 5, 2)->nullable()->after('completion_note');
            }

            if (! Schema::hasColumn('tasks', 'recurrence_rule')) {
                $table->string('recurrence_rule', 120)->nullable()->after('performance_score');
            }

            if (! Schema::hasColumn('tasks', 'template_key')) {
                $table->string('template_key', 100)->nullable()->after('recurrence_rule')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            foreach (['template_key', 'recurrence_rule', 'performance_score', 'completion_note', 'completed_at', 'completed_minutes', 'estimated_minutes'] as $column) {
                if (Schema::hasColumn('tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
