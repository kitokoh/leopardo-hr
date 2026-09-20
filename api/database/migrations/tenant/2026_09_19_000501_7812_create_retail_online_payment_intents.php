<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7812 (BC-17 RETAIL, epic Leopardo Marche) - Paiement en ligne du
 * checkout public : table des intents de paiement
 * `retail_online_payment_intents`.
 *
 * Un intent est cree au checkout quand `payment_method = online` et suit le
 * cycle de vie `pending|processing|succeeded|failed|expired|refunded`
 * (webhook signe + reconciliation `retail:payments:reconcile`). Montants en
 * minor units UNIQUEMENT. `intent_reference` (64 hex) est la cle publique
 * partagee avec le PSP (metadata) — UNIQUE par tenant, et de facto globale
 * (aleatoire 256 bits) pour la resolution cross-tenant du webhook.
 * `provider` est un code court (`chargily|mock`) — l'abstraction provider
 * est LOCALE au module Retail (config env), en attendant les profils de
 * paiement tenant BC-21 (PR #7732, non merge).
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6). Idempotente + down() complet avec gardes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_online_payment_intents')) {
            Schema::create('retail_online_payment_intents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('order_id');
                $table->string('intent_reference', 64);
                $table->string('provider', 40);
                $table->unsignedBigInteger('amount_minor');
                $table->char('currency', 3)->default('DZD');
                $table->string('status', 20)->default('pending');
                $table->text('checkout_url')->nullable();
                $table->json('provider_payload')->nullable();
                $table->string('idempotency_key', 80)->nullable();

                $table->timestamps();

                $table->index(['company_id', 'order_id'], 'retail_payment_intents_company_order_idx');
                $table->index(['company_id', 'status'], 'retail_payment_intents_company_status_idx');
            });

            // Uniques nommes, crees hors Blueprint pour rester idempotents.
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS retail_payment_intents_company_reference_unique ON retail_online_payment_intents (company_id, intent_reference)');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS retail_payment_intents_company_idempotency_unique ON retail_online_payment_intents (company_id, idempotency_key)');

            // La reconciliation scanne les intents non termines cross-tenant.
            DB::statement('CREATE INDEX IF NOT EXISTS retail_payment_intents_status_created_idx ON retail_online_payment_intents (status, created_at)');

            DB::statement("COMMENT ON TABLE retail_online_payment_intents IS 'Intents de paiement en ligne du checkout marketplace Retail - webhook signe + reconciliation (BC-17/#7812, abstraction provider locale en attendant BC-21).';");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('retail_online_payment_intents')) {
            DB::statement('DROP INDEX IF EXISTS retail_payment_intents_status_created_idx');
            DB::statement('DROP INDEX IF EXISTS retail_payment_intents_company_idempotency_unique');
            DB::statement('DROP INDEX IF EXISTS retail_payment_intents_company_reference_unique');

            Schema::dropIfExists('retail_online_payment_intents');
        }
    }
};
