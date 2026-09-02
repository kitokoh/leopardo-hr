<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6097 (TRAVEL-806) — Abonnements & livraisons de webhooks transporteurs.
 *
 * - `travel_webhook_subscriptions` : URL + secret de signature chiffré au
 *   repos (Crypt::encryptString — jamais exposé en clair par l'API) + liste
 *   d'événements sélectionnables ; un abonnement par (company, carrier).
 * - `travel_webhook_deliveries` : livraison rejouable sans doublon (unique
 *   `(subscription_id, event_id)`), HMAC, retries/backoff, dead-letter
 *   (status failed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_webhook_subscriptions')) {
            Schema::create('travel_webhook_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('carrier_id');
                $table->string('url', 500);
                $table->text('secret_encrypted');
                $table->json('events');
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'carrier_id'], 'travel_webhook_sub_company_carrier_unique');
            });
        }

        if (! schemaTableExists('travel_webhook_deliveries')) {
            Schema::create('travel_webhook_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('subscription_id');
                $table->unsignedBigInteger('event_id');
                $table->string('event_type', 60);
                $table->json('payload_redacted');
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable();
                $table->unsignedInteger('last_http_status')->nullable();
                $table->timestamps();

                $table->unique(['subscription_id', 'event_id'], 'travel_webhook_delivery_event_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_webhook_deliveries');
        Schema::dropIfExists('travel_webhook_subscriptions');
    }
};
