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
        // ── projects ───────────────────────────────────────────────────────────
        Schema::create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 200);
            $table->text('description')->nullable();
            // Statuts alignés avec ProjectStatus Dart enum : active|completed|archived
            $table->enum('status', ['active', 'completed', 'archived'])->default('active');
            $table->unsignedInteger('manager_id')->nullable();
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        // ── tasks ──────────────────────────────────────────────────────────────
        Schema::create('tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('project_id')->nullable();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('assigned_to');
            $table->foreign('assigned_to')->references('id')->on('employees')->cascadeOnDelete();
            $table->unsignedInteger('assigned_by');
            $table->foreign('assigned_by')->references('id')->on('employees')->cascadeOnDelete();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['todo', 'in_progress', 'review', 'done', 'rejected'])->default('todo');
            $table->date('due_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('checklist')->nullable();                 // [{label, done}]
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['assigned_to', 'status']);
            $table->index('due_date');
        });

        // ── task_comments ──────────────────────────────────────────────────────
        Schema::create('task_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('task_id');
            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->unsignedInteger('author_id');
            $table->foreign('author_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->text('content');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('task_id');
        });

        // ── evaluations ────────────────────────────────────────────────────────
        Schema::create('evaluations', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unsignedInteger('evaluator_id');
            $table->foreign('evaluator_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('period', 20);                           // ex: '2026-Q1', '2026-S1', '2026'
            $table->decimal('score', 4, 2)->nullable();             // Score global 0.00 - 10.00
            $table->jsonb('criteria')->default('[]');
            // Structure: [{name, weight, score, comment}]
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('overall_comment')->nullable();
            $table->enum('status', ['draft', 'submitted', 'acknowledged'])->default('draft');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['employee_id', 'period', 'evaluator_id']);
        });

        // ── payrolls ───────────────────────────────────────────────────────────
        Schema::create('payrolls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_month');           // 1-12
            $table->unsignedSmallInteger('period_year');
            // gross_salary = salary_base + overtime + bonuses (CALCULÉ par PayrollService)
            // DIFFÉRENT de employees.salary_base (fixe)
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->jsonb('bonuses')->default('[]');                // [{name, amount}]
            $table->jsonb('deductions')->default('[]');             // [{name, amount}]
            $table->jsonb('cotisations')->default('[]');            // [{name, rate, base, amount}]
            $table->decimal('ir_amount', 12, 2)->default(0);       // Impôt sur le revenu
            $table->decimal('advance_deduction', 12, 2)->default(0); // Déduction avance active
            $table->decimal('absence_deduction', 12, 2)->default(0); // Déduction absences non payées
            $table->decimal('penalty_deduction', 12, 2)->default(0); // Pénalités retard (plafond 1j)
            $table->decimal('net_salary', 12, 2)->default(0);      // Net à payer
            $table->string('pdf_path', 255)->nullable();
            $table->enum('status', ['draft', 'validated', 'paid'])->default('draft');
            $table->unsignedInteger('validated_by')->nullable();
            $table->foreign('validated_by')->references('id')->on('employees')->nullOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['employee_id', 'period_year', 'period_month']);
            $table->index(['period_year', 'period_month']);

            $table->comment('Un bulletin par employé par mois. gross_salary est calculé par PayrollService (pas = employees.salary_base)');
        });

        // ── payroll_export_batches ─────────────────────────────────────────────
        Schema::create('payroll_export_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('period', 20);
            $table->enum('bank_format', ['DZ_GENERIC', 'MA_CIH', 'FR_SEPA', 'TN_GENERIC', 'TR_GENERIC'])
                  ->default('DZ_GENERIC');
            $table->string('file_path', 255)->nullable();
            $table->unsignedSmallInteger('employee_count')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->unsignedInteger('generated_by');
            $table->foreign('generated_by')->references('id')->on('employees')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        // ── company_settings ───────────────────────────────────────────────────
        Schema::create('company_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index()->unique();
            // Onboarding
            $table->boolean('onboarding_step_1_done')->default(false); // Employés ajoutés
            $table->boolean('onboarding_step_2_done')->default(false); // Planning configuré
            $table->boolean('onboarding_step_3_done')->default(false); // App mobile téléchargée
            $table->boolean('onboarding_step_4_done')->default(false); // Premier pointage
            $table->boolean('onboarding_completed')->default(false);
            $table->timestampTz('onboarding_skipped_at')->nullable();
            // Paramètres RH
            $table->unsignedSmallInteger('payroll_day')->default(25); // Jour de calcul paie
            $table->boolean('notify_attendance_late')->default(true);
            $table->boolean('notify_absence_pending')->default(true);
            $table->boolean('notify_advance_pending')->default(true);
            $table->boolean('notify_payroll_generated')->default(true);
            // Identifiants légaux (non chiffrés — pas RGPD)
            $table->string('legal_id', 50)->nullable();            // SIRET/NIF/RC/MF/Vergi No
            $table->string('legal_id_label', 30)->nullable();      // 'SIRET', 'NIF', 'RC'...
            $table->string('social_security_id', 50)->nullable();  // CNAS/CNSS numéro affilié
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        // ── audit_logs ─────────────────────────────────────────────────────────
        // Rempli via Observer Eloquent (Employee, Payroll, Absence, SalaryAdvance)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id')->nullable();     // Qui a fait l'action
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->string('action', 100);                          // ex: 'employee.updated', 'payroll.validated'
            $table->string('target_type', 50);                      // Nom du modèle : 'Employee', 'Payroll'...
            $table->unsignedBigInteger('target_id');                // ID de la ressource modifiée
            $table->jsonb('changes')->nullable();                   // {fields, old, new}
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index('action');
            $table->index('created_at');                            // Pour purge après 24 mois

            $table->comment('Rempli par Observer Eloquent. Rétention 24 mois (RGPD). Voir 09_tests_qualite/23_AUDIT_LOG_STRATEGY.md');
        });

        // ── notifications ──────────────────────────────────────────────────────
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('type', 100);                            // ex: 'absence.approved', 'payroll.ready'
            $table->string('title', 200);
            $table->text('body');
            $table->jsonb('data')->nullable();                      // Payload custom (IDs, liens)
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
        Schema::dropIfExists('payroll_export_batches');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
    }
};
