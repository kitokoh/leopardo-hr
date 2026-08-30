<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5832 (EDU-016) — frais scolaires et contrat Accounting.
 *
 * `edu_fee_types` : catalogue des frais d'un établissement (code unique par
 * tenant, montant, devise, fréquence de facturation). Rattaché optionnellement
 * à un campus (FK composite anti cross-tenant).
 *
 * `edu_fee_charges` : facturation d'un frais à un élève pour une année
 * scolaire. Statuts bornés (pending|partial|paid|waived|cancelled) ;
 * `external_id` unique PAR TENANT → rejeu idempotent des intégrations ;
 * montant borné (CHECK >= 0).
 *
 * `edu_fee_payments` : encaissements sur une charge. `external_id` unique par
 * tenant → idempotence ; méthode bornée (cash|transfer|card|mobile_money|
 * other) ; montant strictement positif ; le contrôle de non-surdébit est
 * porté par le service (EDU_FEE_OVERPAYMENT).
 *
 * `edu_accounting_entries` : contrat Accounting — lignes d'écriture
 * équilibrées (débit = crédit) produites par EduAccountingEntryService à la
 * création d'une charge (créance client / produits), à l'encaissement
 * (banque/caisse / créance) et à l'abandon (pertes / créance). Le module
 * EduManager reste maître de la facturation ; le module Accounting consomme
 * ces lignes (pattern PayrollAccountingEntry #5239). UNIQUE
 * (company_id, source_type, source_id, account_code) → régénération
 * idempotente sans doublon (rapprochement audité).
 *
 * Toutes les tables : `company_id` uuid NON nullable, index tenant-first,
 * FK composites (id, company_id) anti cross-tenant, gardes schemaTableExists
 * (idempotence #1613), CHECKs gardés pg_constraint. Migration additive —
 * rollback : down() supprime dans l'ordre inverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_fee_types')) {
            Schema::create('edu_fee_types', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('campus_id')->nullable()->index();
                $table->string('code', 50);
                $table->string('label', 191);
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                // once | term | monthly — CHECK edu_fee_types_frequency_check
                $table->string('billing_frequency', 20)->default('once');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'edu_fee_types_company_code_unique');
                $table->unique(['id', 'company_id'], 'edu_fee_types_id_company_unique');
                $table->index(['company_id', 'is_active'], 'edu_fee_types_company_active_idx');

                $table->foreign(['campus_id', 'company_id'], 'edu_fee_types_campus_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_campuses')
                    ->nullOnDelete();
            });

            $schema = resolveTableSchema('edu_fee_types');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_fee_types_frequency_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_fee_types\" ADD CONSTRAINT edu_fee_types_frequency_check "
                    ."CHECK (billing_frequency IN ('once','term','monthly')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_fee_types_amount_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_fee_types\" ADD CONSTRAINT edu_fee_types_amount_check "
                    .'CHECK (amount >= 0); END IF; END $$'
                );
            }
        }

        if (! schemaTableExists('edu_fee_charges')) {
            Schema::create('edu_fee_charges', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('fee_type_id')->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                // pending | partial | paid | waived | cancelled
                $table->string('status', 20)->default('pending');
                $table->date('due_date')->nullable();
                $table->string('external_id', 100)->nullable();
                $table->unsignedInteger('charged_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'external_id'], 'edu_fee_charges_company_external_unique');
                $table->unique(['id', 'company_id'], 'edu_fee_charges_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_fee_charges_company_status_idx');
                $table->index(['company_id', 'student_id'], 'edu_fee_charges_company_student_idx');
                $table->index(['company_id', 'due_date'], 'edu_fee_charges_company_due_idx');

                $table->foreign(['student_id', 'company_id'], 'edu_fee_charges_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
                $table->foreign(['fee_type_id', 'company_id'], 'edu_fee_charges_type_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_fee_types')
                    ->cascadeOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_fee_charges_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_fee_charges');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_fee_charges_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_fee_charges\" ADD CONSTRAINT edu_fee_charges_status_check "
                    ."CHECK (status IN ('pending','partial','paid','waived','cancelled')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_fee_charges_amount_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_fee_charges\" ADD CONSTRAINT edu_fee_charges_amount_check "
                    .'CHECK (amount >= 0); END IF; END $$'
                );
            }
        }

        if (! schemaTableExists('edu_fee_payments')) {
            Schema::create('edu_fee_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('fee_charge_id')->index();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('DZD');
                // cash | transfer | card | mobile_money | other
                $table->string('method', 20);
                $table->string('reference', 120)->nullable();
                $table->string('external_id', 100)->nullable();
                $table->timestamp('paid_at');
                $table->unsignedInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'external_id'], 'edu_fee_payments_company_external_unique');
                $table->unique(['id', 'company_id'], 'edu_fee_payments_id_company_unique');
                $table->index(['company_id', 'fee_charge_id'], 'edu_fee_payments_company_charge_idx');
                $table->index(['company_id', 'paid_at'], 'edu_fee_payments_company_paid_idx');

                $table->foreign(['fee_charge_id', 'company_id'], 'edu_fee_payments_charge_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_fee_charges')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_fee_payments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_fee_payments_method_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_fee_payments\" ADD CONSTRAINT edu_fee_payments_method_check "
                    ."CHECK (method IN ('cash','transfer','card','mobile_money','other')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_fee_payments_amount_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_fee_payments\" ADD CONSTRAINT edu_fee_payments_amount_check "
                    .'CHECK (amount > 0); END IF; END $$'
                );
            }
        }

        if (! schemaTableExists('edu_accounting_entries')) {
            Schema::create('edu_accounting_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // fee_charge | fee_payment | fee_waiver
                $table->string('source_type', 30);
                $table->unsignedBigInteger('source_id');
                $table->date('entry_date');
                $table->string('account_code', 20);
                $table->string('account_label', 191);
                $table->decimal('debit', 12, 2)->default(0);
                $table->decimal('credit', 12, 2)->default(0);
                $table->string('reference', 120);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'source_type', 'source_id', 'account_code'],
                    'edu_accounting_entries_source_account_unique'
                );
                $table->index(['company_id', 'entry_date'], 'edu_accounting_entries_company_date_idx');
                $table->index(['company_id', 'source_type', 'source_id'], 'edu_accounting_entries_source_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_accounting_entries');
        Schema::dropIfExists('edu_fee_payments');
        Schema::dropIfExists('edu_fee_charges');
        Schema::dropIfExists('edu_fee_types');
    }
};
