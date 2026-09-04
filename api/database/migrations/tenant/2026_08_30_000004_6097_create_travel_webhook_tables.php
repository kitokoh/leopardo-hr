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
        // MIGRATION FANTÔME (merges travel parallèles, consolidation 2026-09-04) —
        // la table travel_webhook_deliveries/subscriptions canonique (UUID, enum
        // TravelWebhookDeliveryStatus, colonnes outbox_event_id/last_status_code/
        // delivered_at) est créée par 2026_08_30_000925_6097 (PR #6521, modèle et
        // factories alignés). Cette variante bigint antérieure (7a87cffd4) s'exécutait
        // en premier et figeait un schéma obsolète. No-op volontaire.
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_webhook_deliveries');
        Schema::dropIfExists('travel_webhook_subscriptions');
    }
};
