<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5239 — Phase C : ordre de virement.
 *
 * Un ordre de virement est préparé depuis le net par employé d'un
 * `PayrollRun` validé (réutilisation des formats d'export banque existants :
 * SEPA/CNEP…), puis exécuté par le comptable (référence banque + date) et
 * rapproché. Voir spec #5239 — flux paie → comptabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('payroll_run_id')->index();
            $table->string('status', 24)->default('prepared')->index();
            $table->string('format', 32);
            $table->string('file_path', 512)->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->unsignedInteger('transfer_count')->default(0);
            $table->string('bank_reference', 128)->nullable();
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_payment_order_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payment_order_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->decimal('net_amount', 14, 2);
            $table->string('iban', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payment_order_items');
        Schema::dropIfExists('payroll_payment_orders');
    }
};
