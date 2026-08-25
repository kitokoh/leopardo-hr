<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5239 — Phase C : écritures salariales automatiques.
 *
 * Table de persistance des lignes d'écriture produites par
 * `PayrollAccountingExportService::journalLines()` (socle #5256) lors de la
 * validation d'un `PayrollRun`. Le module Payroll reste maître du calcul ;
 * le module Accounting consommera ces lignes (pont Paie → Comptabilité).
 *
 * Contrainte d'unicité (payroll_run_id, pay_slip_id, account_code) :
 * la régénération des écritures d'un run est IDEMPOTENTE (remplacement des
 * lignes du run), cf. spec #5239 — flux paie → comptabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('payroll_accounting_entries')) {
            return; // renommage #5431 : re-run sans effet (table déjà créée)
        }

        Schema::create('payroll_accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('payroll_run_id')->index();
            $table->unsignedBigInteger('pay_slip_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->date('date');
            $table->string('account_code', 32);
            $table->string('account_label', 255);
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('reference', 128);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Idempotence de la régénération : une ligne par (run, bulletin, compte).
            $table->unique(['payroll_run_id', 'pay_slip_id', 'account_code'], 'payroll_accounting_entries_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_accounting_entries');
    }
};
