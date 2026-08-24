<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des écritures salariales — flux Paie → Comptabilité (issue #5239,
 * Phase C, Partie 1).
 *
 * Persiste EXACTEMENT le résultat de PayrollAccountingExportService::journalLines()
 * (moteur #5256, lecture seule) : équilibre débit = crédit garanti par
 * construction, zéro re-calcul. Idempotent via la contrainte UNIQUE
 * (company_id, payroll_run_id, pay_slip_id, account_code, debit, credit) —
 * une régénération (événement PayrollRunValidated ou commande
 * accounting:generate-payroll-entries) ne double jamais les écritures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id', 36)->index();
            $table->date('entry_date');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('pay_slip_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('account_code', 16);
            $table->string('account_label', 120);
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('reference', 64);
            $table->string('source', 32)->default('payroll_run');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Idempotence : une régénération ne double jamais les écritures
            // (payroll_run_id globalement unique — clé scopée entreprise par
            // cohérence avec les autres tables du module).
            $table->unique(
                ['company_id', 'payroll_run_id', 'pay_slip_id', 'account_code', 'debit', 'credit'],
                'uq_journal_entries_company_run_account'
            );

            // Lecture par run (endpoint liste + commande de rattrapage).
            $table->index(['company_id', 'payroll_run_id'], 'idx_journal_entries_company_run');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_entries');
    }
};
