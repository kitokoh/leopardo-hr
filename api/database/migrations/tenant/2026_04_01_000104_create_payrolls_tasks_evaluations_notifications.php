<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant 0005 — projects, tasks, evaluations, payrolls,
 *                          company_settings, audit_logs, notifications
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->jsonb('members')->default('[]'); // [employee_ids]
            $table->enum('status', ['active', 'completed', 'archived'])->default('active');
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('employees');
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('CREATE INDEX IF NOT EXISTS idx_proj_members ON projects USING GIN(members)');

        Schema::create('tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('employees');
            $table->jsonb('assigned_to')->default('[]'); // [employee_ids]
            $table->unsignedInteger('project_id')->nullable();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->timestampTz('due_date');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['todo', 'inprogress', 'review', 'done', 'rejected', 'cancelled'])->default('todo');
            $table->string('category', 100)->nullable();
            $table->jsonb('checklist')->nullable();
            $table->enum('visibility', ['private', 'visible'])->default('visible');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['status', 'due_date']);
        });
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tasks_assigned ON tasks USING GIN(assigned_to)');

        Schema::create('task_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('task_id');
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->unsignedInteger('author_id');
            $table->foreign('author_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->text('content');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['task_id', 'created_at']);
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unsignedInteger('evaluator_id');
            $table->foreign('evaluator_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('period', 20);
            $table->decimal('score', 4, 2)->nullable();
            $table->jsonb('criteria')->default('[]');
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('overall_comment')->nullable();
            $table->enum('status', ['draft', 'submitted', 'acknowledged'])->default('draft');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['employee_id', 'period', 'evaluator_id']);
        });

        Schema::create('payrolls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->jsonb('bonuses')->default('[]');
            $table->jsonb('deductions')->default('[]');
            $table->jsonb('cotisations')->default('[]');
            $table->decimal('ir_amount', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('absence_deduction', 12, 2)->default(0);
            $table->decimal('penalty_deduction', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->string('pdf_path', 255)->nullable();
            $table->enum('status', ['draft', 'validated'])->default('draft');
            $table->unsignedInteger('validated_by')->nullable();
            $table->foreign('validated_by')->references('id')->on('employees')->nullOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['employee_id', 'period_year', 'period_month']);
            $table->index(['period_year', 'period_month', 'status']);
        });

        Schema::create('payroll_export_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedSmallInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->string('bank_format', 30)->default('DZ_GENERIC');
            $table->string('file_path', 255);
            $table->decimal('total_amount', 14, 2);
            $table->unsignedInteger('employees_count');
            $table->unsignedInteger('exported_by');
            $table->foreign('exported_by')->references('id')->on('employees')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['period_month', 'period_year']);
        });

        Schema::create('payroll_export_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('batch_id');
            $table->foreign('batch_id')->references('id')->on('payroll_export_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('payroll_id');
            $table->foreign('payroll_id')->references('id')->on('payrolls');
            $table->decimal('amount', 12, 2);

            $table->unique(['batch_id', 'payroll_id']);
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value');
            $table->enum('value_type', ['string', 'integer', 'boolean', 'json', 'decimal'])->default('string');
            $table->timestampTz('updated_at')->useCurrent();
        });
        DB::statement(
            "COMMENT ON TABLE company_settings IS 'Clés valides documentées dans docs/dossierdeConception/18_schemas_sql/07_SCHEMA_SQL_COMPLET.sql section PARAMÈTRES COMPANY_SETTINGS PAR DÉFAUT. Toute nouvelle clé doit être ajoutée à TenantService.getDefaultSettings() ET documentée ici. Ne jamais insérer une clé non documentée.'"
        );

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id')->nullable();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->string('action', 100);
            $table->string('target_type', 50);
            $table->unsignedBigInteger('target_id');
            $table->jsonb('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index('action');
            $table->index('created_at');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('type', 100);
            $table->string('title', 200);
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['employee_id', 'is_read']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('payroll_export_items');
        Schema::dropIfExists('payroll_export_batches');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
    }
};
