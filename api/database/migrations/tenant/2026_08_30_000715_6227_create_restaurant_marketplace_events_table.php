<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6227 (RESTO-806) — Événements marketplace (apps de livraison).
 *
 * Journal d'audit + idempotence des webhooks entrants (Uber Eats, Glovo) :
 * un même événement provider ne crée jamais deux commandes
 * (UNIQUE company_id, provider, event_id + UNIQUE company_id, idempotency_key).
 * `payload_redacted` ne conserve que les champs métier nécessaires (jamais de
 * données personnelles superflues — audit RGPD RESTO-904).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_marketplace_events')) {
            Schema::create('restaurant_marketplace_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('provider', 30);
                $table->string('event_id', 120);
                $table->string('event_type', 60)->default('order.created');
                $table->string('idempotency_key', 120);
                $table->string('status', 20)->default('received'); // received | processed | failed
                $table->json('payload_redacted')->nullable();
                $table->string('last_error', 500)->nullable();
                $table->string('order_reference', 40)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'provider', 'event_id'], 'restaurant_marketplace_events_company_provider_event_unique');
                $table->unique(['company_id', 'idempotency_key'], 'restaurant_marketplace_events_company_idempotency_unique');
                $table->index(['company_id', 'status', 'created_at'], 'restaurant_marketplace_events_company_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_marketplace_events');
    }
};
