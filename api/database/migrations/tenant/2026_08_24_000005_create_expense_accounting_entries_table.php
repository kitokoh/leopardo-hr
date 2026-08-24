<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5235 — Phase C : écritures comptables des notes de frais.
 *
 * À l'approbation d'un `ExpenseClaim` (statut `approved`), l'observer
 * `ExpenseAccountingEntryObserver` persiste 2 lignes équilibrées
 * (D 625 charges / C 512 banque) avec référence `EXPENSE-CLAIM-{id}`.
 *
 * Contrainte d'unicité (expense_claim_id, account_code) : régénération
 * IDEMPOTENTE (remplacement des lignes du claim).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('expense_claim_id')->index();
            $table->date('date');
            $table->string('account_code', 32);
            $table->string('account_label', 255);
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('reference', 128);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Idempotence de la régénération : une ligne par (claim, compte).
            $table->unique(['expense_claim_id', 'account_code'], 'expense_accounting_entries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_accounting_entries');
    }
};
