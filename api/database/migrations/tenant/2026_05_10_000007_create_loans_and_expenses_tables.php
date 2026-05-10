<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // ── Employee Loans ──────────────────────────────────────────────────
        if (! Schema::hasTable('employee_loans')) {
            Schema::create('employee_loans', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->enum('loan_type', ['personal', 'housing', 'vehicle', 'education', 'emergency'])->default('personal');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                $table->decimal('interest_rate', 5, 2)->default(0);
                $table->unsignedSmallInteger('installments');
                $table->decimal('installment_amount', 12, 2);
                $table->date('start_date');
                $table->enum('status', ['draft', 'pending_approval', 'approved', 'disbursed', 'repaying', 'closed', 'defaulted'])->default('draft');
                $table->unsignedInteger('approved_by')->nullable();
                $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
                $table->timestampTz('disbursed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestampsTz();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('loan_repayments')) {
            Schema::create('loan_repayments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_loan_id');
                $table->foreign('employee_loan_id')->references('id')->on('employee_loans')->cascadeOnDelete();
                $table->uuid('company_id')->index();
                $table->date('due_date');
                $table->decimal('amount', 12, 2);
                $table->decimal('principal', 12, 2);
                $table->decimal('interest', 12, 2)->default(0);
                $table->enum('status', ['pending', 'paid', 'overdue', 'waived'])->default('pending');
                $table->timestampTz('paid_at')->nullable();
                $table->unsignedInteger('payroll_id')->nullable();
                $table->timestampsTz();

                $table->index(['employee_loan_id', 'due_date']);
            });
        }

        // ── Expense Claims ──────────────────────────────────────────────────
        if (! Schema::hasTable('expense_claims')) {
            Schema::create('expense_claims', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('DZD');
                $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'paid'])->default('draft');
                $table->timestampTz('submitted_at')->nullable();
                $table->timestampTz('approved_at')->nullable();
                $table->timestampTz('paid_at')->nullable();
                $table->unsignedInteger('approved_by')->nullable();
                $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
                $table->string('payment_reference', 100)->nullable();
                $table->timestampsTz();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('expense_items')) {
            Schema::create('expense_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expense_claim_id');
                $table->foreign('expense_claim_id')->references('id')->on('expense_claims')->cascadeOnDelete();
                $table->enum('category', ['transport', 'meals', 'accommodation', 'office', 'communication', 'other'])->default('other');
                $table->string('description', 255);
                $table->decimal('amount', 12, 2);
                $table->date('date');
                $table->string('receipt_path', 500)->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('employee_loans');
    }
};
