<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('leave_policies')) {
            Schema::create('leave_policies', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('absence_type_id');
                $table->foreign('absence_type_id')->references('id')->on('absence_types');
                $table->string('name', 150);
                $table->enum('accrual_type', ['monthly', 'yearly', 'manual'])->default('yearly');
                $table->decimal('accrual_amount', 6, 2)->default(0);
                $table->decimal('max_balance', 6, 2)->nullable();
                $table->boolean('carry_forward')->default(false);
                $table->decimal('carry_forward_max', 6, 2)->nullable();
                $table->unsignedSmallInteger('carry_forward_expiry_days')->nullable();
                $table->boolean('requires_approval')->default(true);
                $table->unsignedSmallInteger('approval_levels')->default(1);
                $table->unsignedSmallInteger('min_notice_days')->default(0);
                $table->unsignedSmallInteger('max_consecutive_days')->nullable();
                $table->jsonb('applicable_roles')->nullable();
                $table->boolean('active')->default(true);
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('leave_accruals')) {
            Schema::create('leave_accruals', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->unsignedBigInteger('leave_policy_id');
                $table->foreign('leave_policy_id')->references('id')->on('leave_policies');
                $table->decimal('amount', 6, 2);
                $table->enum('type', ['accrual', 'carry_forward', 'manual_adjustment', 'deduction'])->default('accrual');
                $table->string('description', 255)->nullable();
                $table->date('effective_date');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['employee_id', 'effective_date']);
            });
        }

        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->unsignedInteger('absence_type_id');
                $table->foreign('absence_type_id')->references('id')->on('absence_types');
                $table->decimal('balance', 6, 2)->default(0);
                $table->decimal('used', 6, 2)->default(0);
                $table->decimal('pending', 6, 2)->default(0);
                $table->unsignedSmallInteger('year');
                $table->timestampTz('updated_at')->useCurrent();

                $table->unique(['employee_id', 'absence_type_id', 'year']);
            });
        }

        if (! Schema::hasTable('approval_workflows')) {
            Schema::create('approval_workflows', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 150);
                $table->string('model_type', 100);
                $table->jsonb('levels');
                $table->decimal('auto_approve_below', 12, 2)->nullable();
                $table->unsignedSmallInteger('escalation_hours')->nullable();
                $table->boolean('active')->default(true);
                $table->timestampsTz();

                $table->index(['company_id', 'model_type']);
            });
        }

        if (! Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('workflow_id');
                $table->foreign('workflow_id')->references('id')->on('approval_workflows');
                $table->string('approvable_type', 100);
                $table->unsignedBigInteger('approvable_id');
                $table->unsignedInteger('requester_id');
                $table->foreign('requester_id')->references('id')->on('employees');
                $table->unsignedSmallInteger('current_level')->default(1);
                $table->enum('status', ['pending', 'approved', 'rejected', 'escalated', 'cancelled'])->default('pending');
                $table->timestampsTz();

                $table->index(['approvable_type', 'approvable_id']);
            });
        }

        if (! Schema::hasTable('approval_decisions')) {
            Schema::create('approval_decisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('approval_request_id');
                $table->foreign('approval_request_id')->references('id')->on('approval_requests')->cascadeOnDelete();
                $table->unsignedSmallInteger('level');
                $table->unsignedInteger('approver_id');
                $table->foreign('approver_id')->references('id')->on('employees');
                $table->enum('decision', ['approved', 'rejected']);
                $table->text('comment')->nullable();
                $table->timestampTz('decided_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_accruals');
        Schema::dropIfExists('leave_policies');
    }
};
