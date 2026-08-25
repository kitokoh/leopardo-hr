<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5235 — Phase C : notes de frais → écritures comptables.
 *
 * Table de persistance des lignes d'écriture générées à l'approbation d'une
 * `ExpenseClaim` (module Expense). Le module Expense reste maître du workflow
 * des notes de frais ; ces lignes sont consommées par le module Accounting
 * (référence traçable `EXPENSE-{id}`).
 *
 * Contrainte d'unicité (expense_claim_id, account_code) : la régénération
 * des écritures d'une note est IDEMPOTENTE (remplacement des lignes de la
 * note), cf. spec #5235 — expense → comptabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('expense_claim_id')->index();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->date('date');
            $table->string('account_code', 32);
            $table->string('account_label', 255);
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('reference', 128);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Idempotence de la régénération : une ligne par (note, compte).
            $table->unique(['expense_claim_id', 'account_code'], 'expense_accounting_entries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_accounting_entries');
    }
};
