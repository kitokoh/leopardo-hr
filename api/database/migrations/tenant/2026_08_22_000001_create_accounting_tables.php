<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5221 (Phase A).
 *
 * Tables tenant (`shared_tenants`) du module Accounting, conformément à
 * `docs/architecture/COMPTABILITE_CONCEPTION.md` §4-5 :
 *   - accounting_contacts      : tiers de facturation (client/fournisseur)
 *   - accounting_documents     : facture, proforma, devis, avoir, irsaliye, reçu
 *   - accounting_document_lines: lignes de document
 *   - accounting_payments      : encaissements/règlements + rapprochement
 *   - accounting_settings      : paramétrage par entreprise (numérotation, TVA…)
 *
 * Règles :
 *   - migration additive et idempotente (garde schemaTableExists) ;
 *   - company_id uuid NON nullable — l'isolation tenant est portée par le trait
 *     BelongsToCompany (garde fail-closed #3727) ;
 *   - colonnes sensibles en `text` pour supporter les casts `encrypted` /
 *     `encrypted:array` (mêmes politiques que Payroll/Cabinet, RGPD loi 18-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_contacts')) {
            Schema::create('accounting_contacts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                // customer | supplier | both
                $table->string('type', 20)->default('customer');
                $table->string('name', 255);
                $table->string('legal_name', 255)->nullable();
                $table->text('tax_id')->nullable(); // NIF — chiffré (cast encrypted)
                $table->string('email', 255)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('address', 500)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('payment_terms', 60)->nullable();
                $table->string('language', 10)->nullable();
                // manual | marketing_lead
                $table->string('source', 30)->default('manual');
                $table->unsignedBigInteger('marketing_lead_id')->nullable()->index();
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamps();

                $table->index(['company_id', 'type']);
            });
        }

        if (! schemaTableExists('accounting_documents')) {
            Schema::create('accounting_documents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                // invoice | proforma | quote | credit_note | delivery_note | receipt
                $table->string('type', 30);
                $table->string('number', 60);
                // draft | sent | partially_paid | paid | cancelled | overdue
                $table->string('status', 30)->default('draft');
                $table->unsignedBigInteger('contact_id')->nullable()->index();
                $table->string('project_ref', 120)->nullable();
                $table->date('issue_date');
                $table->date('due_date')->nullable();
                $table->date('delivery_date')->nullable();
                $table->string('currency', 10)->nullable();
                $table->decimal('exchange_rate', 15, 6)->nullable();
                $table->decimal('subtotal_ht', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_ttc', 15, 2)->default(0);
                $table->decimal('tva_rate', 8, 4)->nullable();
                $table->text('notes')->nullable();
                $table->text('footer_mentions')->nullable();
                $table->string('pdf_path', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamps();

                $table->foreign('contact_id')->references('id')->on('accounting_contacts')->nullOnDelete();
                $table->index(['company_id', 'type', 'status']);
                $table->unique(['company_id', 'number']);
            });
        }

        if (! schemaTableExists('accounting_document_lines')) {
            Schema::create('accounting_document_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('document_id')->index();
                $table->string('description', 500);
                $table->decimal('quantity', 15, 4)->default(1);
                $table->decimal('unit_price', 15, 4)->default(0);
                $table->decimal('discount', 15, 4)->default(0);
                $table->string('tax_id', 60)->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('document_id')->references('id')->on('accounting_documents')->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('accounting_payments')) {
            Schema::create('accounting_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('document_id')->index();
                $table->decimal('amount', 15, 2);
                // cash | bank_transfer | check | card | other
                $table->string('method', 30);
                $table->text('reference')->nullable(); // n° chèque/RIB — chiffré
                $table->date('received_at')->nullable();
                $table->date('reconciled_at')->nullable();
                // pending | recorded | matched
                $table->string('status', 20)->default('pending');
                $table->text('metadata')->nullable(); // chiffré (cast encrypted:array)
                $table->timestamps();

                $table->foreign('document_id')->references('id')->on('accounting_documents')->cascadeOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        if (! schemaTableExists('accounting_settings')) {
            Schema::create('accounting_settings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->unique();
                $table->json('number_series')->nullable(); // par type : préfixe + compteur
                $table->json('tva_rates')->nullable();     // taux par défaut (pays)
                $table->text('legal_mentions')->nullable();
                $table->string('template_style', 60)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('payment_terms', 60)->nullable();
                $table->string('document_language', 10)->default('fr');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('accounting_payments');
        Schema::dropIfExists('accounting_document_lines');
        Schema::dropIfExists('accounting_documents');
        Schema::dropIfExists('accounting_contacts');
    }
};
