<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $schema = resolveTableSchema('tasks');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.tasks", function (Blueprint $table): void {
            if (! schemaHasColumn('tasks', 'estimated_minutes')) {
                $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('priority');
            }

            if (! schemaHasColumn('tasks', 'completed_minutes')) {
                $table->unsignedSmallInteger('completed_minutes')->nullable()->after('estimated_minutes');
            }

            if (! schemaHasColumn('tasks', 'completed_at')) {
                $table->timestampTz('completed_at')->nullable()->after('completed_minutes');
            }

            if (! schemaHasColumn('tasks', 'completion_note')) {
                $table->text('completion_note')->nullable()->after('completed_at');
            }

            if (! schemaHasColumn('tasks', 'performance_score')) {
                $table->decimal('performance_score', 5, 2)->nullable()->after('completion_note');
            }

            if (! schemaHasColumn('tasks', 'recurrence_rule')) {
                $table->string('recurrence_rule', 120)->nullable()->after('performance_score');
            }

            if (! schemaHasColumn('tasks', 'template_key')) {
                $table->string('template_key', 100)->nullable()->after('recurrence_rule')->index();
            }

            if (! schemaHasColumn('tasks', 'category')) {
                $table->string('category', 100)->nullable()->after('status');
            }

            if (! schemaHasColumn('tasks', 'checklist')) {
                $table->jsonb('checklist')->nullable()->after('category');
            }

            if (! schemaHasColumn('tasks', 'visibility')) {
                $table->string('visibility', 20)->default('visible')->after('checklist');
            }
        });
    }

    public function down(): void
    {
        $schema = resolveTableSchema('tasks');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.tasks", function (Blueprint $table): void {
            foreach (['visibility', 'checklist', 'category', 'template_key', 'recurrence_rule', 'performance_score', 'completion_note', 'completed_at', 'completed_minutes', 'estimated_minutes'] as $column) {
                if (schemaHasColumn('tasks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
