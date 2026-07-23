<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-PAY-007 — Employee financial ledger.
 *
 * Every advance, payment, or balance adjustment that touches an employee's
 * pay is recorded here as an immutable, auditable journal entry. This is
 * the single source of truth for "what happened to this employee's money
 * and when" — distinct from `audit_logs` (generic model change tracking)
 * because it carries a running balance and is scoped to financial events
 * only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();

            // Entry classification.
            // 'advance' | 'payment' | 'adjustment'
            $table->string('entry_type', 30)->index();

            // Signed amount: positive credits the employee (payment received),
            // negative debits the employee (advance granted, deduction).
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('DZD');

            // Running balance for this employee immediately after this entry,
            // computed at write time so history reads never need to replay
            // the whole ledger.
            $table->decimal('balance_after', 12, 2);

            $table->string('description', 500)->nullable();

            // Polymorphic link to the source record (SalaryAdvance, PaySlip,
            // PaymentItem, or a manual adjustment with no source model).
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Linked auditable document, if one was generated for this entry.
            $table->unsignedBigInteger('payment_document_id')->nullable();

            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('employees')->nullOnDelete();

            $table->json('metadata')->nullable();

            // Ledger entries are immutable — created_at only, no updated_at.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'employee_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
