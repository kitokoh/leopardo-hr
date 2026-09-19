<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7791 (HC-007, BC-30).
 *
 * health_invoice_payments : paiements encaissés sur une facture de soins
 * (montant, mode, date, référence). Le sur-paiement est REFUSÉ côté
 * application (422 HEALTH_INVOICE_OVERPAYMENT, solde recalculé sous
 * transaction avec verrou sur la facture) ; le statut de la facture
 * (partially_paid | paid) est dérivé du solde EXACT.
 *
 * Invariants portés par le SCHÉMA :
 *   - CHECK montant > 0 ;
 *   - CHECK mode borné cash|card|transfer|cheque|insurance ;
 *   - FK COMPOSITE (invoice_id, company_id) : paiement cross-tenant =
 *     violation FK en base.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_invoice_payments')) {
            Schema::create('health_invoice_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('invoice_id');
                $table->decimal('amount', 12, 2);
                // cash | card | transfer | cheque | insurance — CHECK ci-dessous.
                $table->string('method', 20);
                $table->timestamp('paid_at');
                $table->string('reference', 100)->nullable();
                $table->timestamps();

                $table->unique(['id', 'company_id'], 'health_invoice_payments_id_company_unique');
                $table->index(['company_id', 'invoice_id'], 'health_invoice_payments_company_invoice_idx');
                // Stats de CA par période (HC-007).
                $table->index(['company_id', 'paid_at'], 'health_invoice_payments_company_paid_at_idx');

                // Cross-tenant impossible (FK composite).
                $table->foreign(['invoice_id', 'company_id'], 'health_invoice_payments_invoice_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_invoices')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('health_invoice_payments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_invoice_payments_amount_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_invoice_payments\" ADD CONSTRAINT health_invoice_payments_amount_check "
                    .'CHECK (amount > 0); END IF; END $$'
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_invoice_payments_method_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_invoice_payments\" ADD CONSTRAINT health_invoice_payments_method_check "
                    ."CHECK (method IN ('cash','card','transfer','cheque','insurance')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_invoice_payments');
    }
};
