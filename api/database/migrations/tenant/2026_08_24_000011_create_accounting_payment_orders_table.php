<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordres de virement salarial — flux Paie → Comptabilité (issue #5239, Phase C).
 *
 * Un ordre est créé depuis un run de paie validé (net par employé), préparé
 * par le comptable (export banque CNEP/SEPA/csv_generic — formats Payroll
 * réutilisés), puis exécuté (référence banque + date) = rapprochement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id', 36)->index();
            $table->unsignedBigInteger('payroll_run_id');
            $table->string('status', 20)->default('draft'); // draft | prepared | executed
            $table->decimal('total_net', 15, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('export_format', 20)->nullable();
            $table->string('export_file', 255)->nullable();
            $table->string('bank_reference', 120)->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Un seul ordre par run et par entreprise (idempotence à la création).
            $table->unique(['company_id', 'payroll_run_id'], 'uq_payment_orders_company_run');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payment_orders');
    }
};
