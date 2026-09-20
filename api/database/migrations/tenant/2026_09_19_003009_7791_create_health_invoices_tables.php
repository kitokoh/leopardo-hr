<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7791 (facturation, BC-30).
 *
 *   - `health_invoices` : factures de soins — numéro `HINV-YYYY-NNNN`
 *     séquentiel PAR TENANT ET PAR ANNÉE, généré serveur (spec §4) ;
 *   - `health_invoice_items` : lignes de facture — prix unitaire FIGÉ au
 *     moment de la facturation (jamais recalculé depuis le catalogue) ;
 *   - `health_invoice_payments` : encaissements — cumul ≤ total
 *     (sur-paiement 422), cumul = total → `paid`, sinon `partially_paid`.
 *
 * Invariants métier (spec §4, portés par le service) : total recalculé
 * serveur (Σ line_total − discount ≥ 0) ; brouillon modifiable, émise non
 * modifiable (annulation seulement) ; une facture émise n'est JAMAIS
 * supprimée physiquement.
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) ;
 *   - UNIQUE(company_id, number) : numéro unique PAR TENANT ;
 *   - FK composites (patient_id/invoice_id/care_act_id, company_id) : une
 *     facture croisant les tenants est STRUCTURELLEMENT impossible ;
 *   - CHECK `status` (draft|issued|paid|partially_paid|cancelled) et
 *     CHECK `method` (cash|card|transfer|mobile|insurance|other).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_invoices')) {
            Schema::create('health_invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // HINV-YYYY-NNNN — séquentiel par tenant/année, généré serveur.
                $table->string('number', 30);
                $table->unsignedBigInteger('patient_id');
                // draft | issued | paid | partially_paid | cancelled
                $table->string('status', 20)->default('draft');
                $table->string('currency', 3);
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->timestampTz('issued_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'number'], 'health_invoices_company_number_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_invoices_id_company_unique');
                $table->index(['company_id', 'status'], 'health_invoices_company_status_idx');
                $table->index(['company_id', 'patient_id'], 'health_invoices_company_patient_idx');
                $table->index(['company_id', 'issued_at'], 'health_invoices_company_issued_idx');

                // Cross-tenant impossible : la paire (patient_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['patient_id', 'company_id'], 'health_invoices_patient_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_patients')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('health_invoices');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_invoices_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_invoices\" ADD CONSTRAINT health_invoices_status_check "
                    ."CHECK (status IN ('draft','issued','paid','partially_paid','cancelled')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('health_invoice_items')) {
            Schema::create('health_invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('invoice_id');
                // Acte du catalogue (nullable : ligne libre) — prix figé à la ligne.
                $table->unsignedBigInteger('care_act_id')->nullable();
                $table->string('label', 191);
                $table->decimal('unit_price', 12, 2);
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('line_total', 12, 2);
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_invoice_items_id_company_unique');
                $table->index(['company_id', 'invoice_id'], 'health_invoice_items_company_invoice_idx');
                $table->index(['company_id', 'care_act_id'], 'health_invoice_items_company_care_act_idx');

                // Cross-tenant impossible : chaque paire (X_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['invoice_id', 'company_id'], 'health_invoice_items_invoice_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_invoices')
                    ->cascadeOnDelete();
                $table->foreign(['care_act_id', 'company_id'], 'health_invoice_items_care_act_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_care_acts')
                    ->nullOnDelete();
            });
        }

        if (! schemaTableExists('health_invoice_payments')) {
            Schema::create('health_invoice_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('invoice_id');
                $table->decimal('amount', 12, 2);
                // cash | card | transfer | mobile | insurance | other
                $table->string('method', 20);
                $table->timestampTz('paid_at');
                $table->string('reference', 191)->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_invoice_payments_id_company_unique');
                $table->index(['company_id', 'invoice_id'], 'health_invoice_payments_company_invoice_idx');
                $table->index(['company_id', 'paid_at'], 'health_invoice_payments_company_paid_idx');

                // Cross-tenant impossible : la paire (invoice_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['invoice_id', 'company_id'], 'health_invoice_payments_invoice_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_invoices')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('health_invoice_payments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_invoice_payments_method_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_invoice_payments\" ADD CONSTRAINT health_invoice_payments_method_check "
                    ."CHECK (method IN ('cash','card','transfer','mobile','insurance','other')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_invoice_payments');
        Schema::dropIfExists('health_invoice_items');
        Schema::dropIfExists('health_invoices');
    }
};
