<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant 0004 — attendance_logs, absence_types, absences,
 *                          leave_balance_logs, salary_advances
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── attendance_logs ────────────────────────────────────────────────────
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unsignedInteger('schedule_id')->nullable();
            $table->foreign('schedule_id')->references('id')->on('schedules')->nullOnDelete();
            // Planning actif AU MOMENT du pointage (snapshot)
            $table->date('date');
            $table->smallInteger('session_number')->default(1);     // 1 = session normale, 2+ = split-shift
            $table->timestampTz('check_in')->nullable();
            $table->timestampTz('check_out')->nullable();
            // RÈGLE : check_in/check_out toujours en UTC côté stockage.
            // CALCULS (retard, HS) se font EN TIMEZONE ENTREPRISE via Carbon::setTimezone()
            $table->enum('method', ['mobile', 'qr', 'biometric', 'manual'])->default('mobile');
            $table->enum('status', ['ontime', 'late', 'absent', 'leave', 'holiday', 'incomplete'])
                  ->default('incomplete');
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->decimal('gps_lat', 10, 8)->nullable();
            $table->decimal('gps_lng', 11, 8)->nullable();
            $table->string('photo_check_in', 255)->nullable();      // Chemin stockage local/R2
            $table->unsignedInteger('corrected_by')->nullable();    // Employee qui a corrigé (manager)
            $table->foreign('corrected_by')->references('id')->on('employees')->nullOnDelete();
            $table->text('correction_note')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // UNE ligne par employé par jour PAR SESSION (split-shift supporté)
            $table->unique(['employee_id', 'date', 'session_number']);
            $table->index(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });

        DB::statement("COMMENT ON COLUMN attendance_logs.session_number IS 'Support split-shift. 1=journée normale. 2+=demi-journées séparées'");
        DB::statement("COMMENT ON COLUMN attendance_logs.check_in IS 'Stocké en UTC. TOUJOURS calculer les retards/HS en timezone entreprise via Carbon::setTimezone(company->timezone)'");

        // ── absence_types ──────────────────────────────────────────────────────
        Schema::create('absence_types', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('code', 20)->unique();                   // ex: 'CONGE_ANNUEL', 'MALADIE'
            $table->boolean('is_paid')->default(true);
            $table->boolean('deducts_leave')->default(true);        // Déduit du solde congés ?
            $table->boolean('requires_proof')->default(false);      // Justificatif obligatoire ?
            $table->unsignedSmallInteger('max_days_once')->nullable(); // Limite par demande
            $table->timestampTz('created_at')->useCurrent();
        });

        // ── absences ───────────────────────────────────────────────────────────
        Schema::create('absences', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unsignedInteger('absence_type_id');
            $table->foreign('absence_type_id')->references('id')->on('absence_types');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_count');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('reason')->nullable();
            $table->string('proof_path', 255)->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
            $table->text('rejected_reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            // Contrainte : end_date >= start_date (ajoutée manuellement)
            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        // Contrainte check dates (PostgreSQL spécifique)
        DB::statement('ALTER TABLE absences ADD CONSTRAINT chk_absence_dates CHECK (end_date >= start_date)');

        // ── leave_balance_logs ────────────────────────────────────────────────
        Schema::create('leave_balance_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->decimal('delta', 5, 2);                         // + accrual ou - consommé
            $table->string('reason', 100);                          // ex: 'accrual_monthly', 'absence_approved'
            $table->unsignedInteger('reference_id')->nullable();    // ID absence ou payroll lié
            $table->decimal('balance_after', 6, 2);                 // Solde après opération
            $table->timestampTz('created_at')->useCurrent();

            $table->index('employee_id');
        });

        // ── salary_advances ────────────────────────────────────────────────────
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('company_id')->nullable()->index();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);                       // Montant demandé
            $table->text('reason')->nullable();

            // STATUTS : pending → approved → active → repaid (+rejected)
            // 'active' = avance approuvée EN COURS de remboursement (PayrollService filtre sur 'active')
            $table->enum('status', ['pending', 'approved', 'rejected', 'active', 'repaid'])
                  ->default('pending');

            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
            $table->text('decision_comment')->nullable();
            $table->unsignedSmallInteger('repayment_months')->default(1);
            $table->decimal('monthly_deduction', 12, 2)->nullable(); // Calculé à l'approbation
            $table->decimal('amount_remaining', 12, 2)->default(0); // Mis à jour par PayrollService
            $table->jsonb('repayment_plan')->nullable();
            // Structure: [{"month":"2026-05","amount":5000,"paid":false}, ...]
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['employee_id', 'status']);
        });

        DB::statement("COMMENT ON COLUMN salary_advances.status IS 'pending→approved→active→repaid. active=en cours de remboursement. PayrollService filtre WHERE status=active'");
        DB::statement("COMMENT ON COLUMN salary_advances.amount_remaining IS 'Mis à jour par PayrollService à chaque déduction mensuelle. 0=totalement remboursé→status devient repaid'");
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('leave_balance_logs');
        Schema::dropIfExists('absences');
        Schema::dropIfExists('absence_types');
        Schema::dropIfExists('attendance_logs');
    }
};
