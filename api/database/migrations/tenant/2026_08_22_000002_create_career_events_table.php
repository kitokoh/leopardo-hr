<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Tenant — career_events (plans de carrière, issue #5259).
 *
 * Trace le parcours professionnel : promotion, augmentation, transfert,
 * changement de poste. Workflow : pending → approved → applied (ou rejected).
 * Le passage à `applied` met à jour employees (position_id, department_id,
 * salary_base) — impact paie sans intervention manuelle (spec
 * docs/specifications/ISSUE_5259_CAREER_PLANS.md §5).
 *
 * Conventions tenant (miroir de evaluations, #104) : company_id uuid nullable
 * (NULL en mode schema), FKs vers employees/positions/departments,
 * timestamps timestampTz.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('career_events')) {
            Schema::create('career_events', function (Blueprint $table) {
                $table->increments('id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->enum('type', ['promotion', 'raise', 'transfer', 'title_change']);
                $table->enum('status', ['pending', 'approved', 'rejected', 'applied'])->default('pending');

                // Traçabilité de → vers (snapshot au moment de la création).
                $table->unsignedInteger('from_position_id')->nullable();
                $table->foreign('from_position_id')->references('id')->on('positions')->nullOnDelete();
                $table->unsignedInteger('to_position_id')->nullable();
                $table->foreign('to_position_id')->references('id')->on('positions')->nullOnDelete();
                $table->unsignedInteger('from_department_id')->nullable();
                $table->foreign('from_department_id')->references('id')->on('departments')->nullOnDelete();
                $table->unsignedInteger('to_department_id')->nullable();
                $table->foreign('to_department_id')->references('id')->on('departments')->nullOnDelete();

                $table->decimal('from_salary', 12, 2)->nullable();
                $table->decimal('to_salary', 12, 2)->nullable();
                $table->date('effective_date');
                $table->string('reason', 500);
                $table->text('notes')->nullable();

                $table->unsignedInteger('approved_by')->nullable();
                $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
                $table->timestampTz('approved_at')->nullable();
                $table->timestampTz('applied_at')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->index(['employee_id', 'status']);
                $table->index(['company_id', 'status', 'effective_date']);

                $table->comment('Plans de carrière — événements de carrière tracés (issue #5259)');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('career_events');
    }
};
