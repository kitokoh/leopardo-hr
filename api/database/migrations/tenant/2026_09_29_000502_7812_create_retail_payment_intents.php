<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7812 (BC-17 RETAIL, epic Leopardo Marche) - Paiement en ligne
 * marketplace (mobile money / PSP, chantier BC-21) : table
 * `retail_payment_intents`.
 *
 * - un seul intent `pending` par commande : index unique PARTIEL Postgres
 *   (company_id, order_id) WHERE status = 'pending' (pattern
 *   retail_pos_sessions #7674) ;
 * - `provider_reference` unique par tenant : cle de reconciliation du
 *   webhook signe (rejeu idempotent) ;
 * - montants en minor units, statuts pending|paid|failed|cancelled.
 *
 * Tenant-scoped (company_id), sans FK, idempotente (conventions migrations
 * tenant §2.6). down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_payment_intents')) {
            Schema::create('retail_payment_intents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('order_id');
                $table->string('provider', 30);
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('amount_minor');
                $table->char('currency', 3)->default('XOF');
                $table->string('provider_reference', 120);
                $table->string('checkout_url', 500)->nullable();
                $table->string('failure_reason', 255)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'provider_reference'], 'retail_payment_intents_company_provider_ref_unique');
                $table->index(['company_id', 'order_id'], 'retail_payment_intents_company_order_idx');
            });

            DB::statement("COMMENT ON TABLE retail_payment_intents IS 'Intents de paiement en ligne marketplace - un seul pending par commande, provider_reference unique par tenant, minor units (BC-17/#7812).'");
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS retail_payment_intents_company_order_pending_unique ON retail_payment_intents (company_id, order_id) WHERE status = 'pending'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_payment_intents');
    }
};
