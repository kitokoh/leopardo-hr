<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-806 (#6097) — Webhooks sortants transporteurs.
 *
 * `travel_webhook_subscriptions` : abonnements par transporteur (URL cible,
 * secret HMAC chiffré, événements sélectionnables, actif/inactif).
 * `travel_webhook_deliveries` : journal de livraison idempotent (une
 * livraison par (subscription, événement) — rejeu sans doublon), statuts
 * pending/sent/failed/dead, retries avec backoff, dead-letter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_webhook_subscriptions')) {
            Schema::create('travel_webhook_subscriptions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id');
                $table->unsignedBigInteger('carrier_id')->nullable();
                $table->string('name', 120);
                $table->string('url', 500);
                $table->text('secret_encrypted');
                $table->jsonb('events');
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'url']);
                $table->index(['company_id', 'active']);
                $table->foreign('carrier_id')->references('id')->on('travel_carriers')->nullOnDelete();
            });
        }

        if (! schemaTableExists('travel_webhook_deliveries')) {
            Schema::create('travel_webhook_deliveries', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id');
                $table->uuid('subscription_id');
                $table->unsignedBigInteger('outbox_event_id')->nullable();
                $table->string('event_type', 80);
                $table->jsonb('payload_redacted');
                $table->string('status', 20)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable();
                $table->unsignedSmallInteger('last_status_code')->nullable();
                $table->string('last_error', 500)->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                // Idempotence : un seul essai de livraison par (abonnement, événement).
                $table->unique(['subscription_id', 'outbox_event_id'], 'travel_webhook_deliveries_unique');
                $table->index(['status', 'next_attempt_at']);
                $table->foreign('subscription_id')->references('id')->on('travel_webhook_subscriptions')->cascadeOnDelete();
                $table->foreign('outbox_event_id')->references('id')->on('travel_outbox_events')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_webhook_deliveries');
        Schema::dropIfExists('travel_webhook_subscriptions');
    }
};
