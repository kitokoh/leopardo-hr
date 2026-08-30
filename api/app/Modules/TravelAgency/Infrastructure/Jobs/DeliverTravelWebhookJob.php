<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Jobs;

use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Infrastructure\Services\TravelWebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * TRAVEL-806 (#6097) — Livraison HMAC d'un webhook transporteur.
 *
 * En-têtes : `X-Travel-Signature` (HMAC-SHA256 du payload canonique),
 * `X-Travel-Timestamp` (anti-rejeu). Erreur transitoire → retry avec
 * backoff exponentiel (attempts++, next_attempt_at) ; attempts ≥ MAX →
 * dead-letter (failed). Rejouable : la contrainte unique
 * `(subscription_id, event_id)` empêche tout doublon.
 */
class DeliverTravelWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1; // Backoff géré manuellement (next_attempt_at)

    public int $timeout = 30;

    public function __construct(
        private readonly int $deliveryId,
        private readonly int $subscriptionId,
    ) {}

    public function handle(TravelWebhookSigner $signer): void
    {
        /** @var TravelWebhookDelivery|null $delivery */
        $delivery = TravelWebhookDelivery::query()->find($this->deliveryId);
        /** @var TravelWebhookSubscription|null $subscription */
        $subscription = TravelWebhookSubscription::query()->find($this->subscriptionId);

        if ($delivery === null || $subscription === null || ! $subscription->active) {
            if ($delivery !== null) {
                $this->deadLetter($delivery, 'Abonnement introuvable ou inactif');
            }

            return;
        }

        if ($delivery->status === TravelWebhookDelivery::STATUS_DELIVERED) {
            return; // Idempotence : déjà livré, on ne re-livre pas.
        }

        $timestamp = now()->toIso8601String();
        $payload = array_merge($delivery->payload_redacted ?? [], [
            'event_type' => $delivery->event_type,
            'delivered_at' => $timestamp,
        ]);
        $canonical = $signer->canonicalPayload($payload);
        $signature = $signer->sign($payload, $subscription->secret());

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Travel-Signature' => $signature,
                    'X-Travel-Timestamp' => $timestamp,
                    'Accept' => 'application/json',
                ])
                ->withBody($canonical, 'application/json')
                ->post($subscription->url);

            $delivery->forceFill([
                'status' => $response->successful() ? TravelWebhookDelivery::STATUS_DELIVERED : TravelWebhookDelivery::STATUS_PENDING,
                'last_http_status' => $response->status(),
            ])->save();

            if (! $response->successful()) {
                $this->retryOrDeadLetter($delivery);
            }
        } catch (Throwable $e) {
            $delivery->forceFill([
                'status' => TravelWebhookDelivery::STATUS_PENDING,
                'last_http_status' => null,
            ])->save();

            $this->retryOrDeadLetter($delivery, $e->getMessage());
        }
    }

    private function retryOrDeadLetter(TravelWebhookDelivery $delivery, ?string $reason = null): void
    {
        $delivery->increment('attempts');

        if ($delivery->attempts >= TravelWebhookDelivery::MAX_ATTEMPTS) {
            $this->deadLetter($delivery->refresh(), $reason ?? 'Échec HTTP');

            return;
        }

        // Backoff exponentiel : 1 min, 2, 4, 8…
        $backoffMinutes = 2 ** max(0, $delivery->attempts - 1);

        $delivery->forceFill([
            'next_attempt_at' => now()->addMinutes($backoffMinutes),
        ])->save();

        self::dispatch($delivery->id, $delivery->subscription_id)
            ->delay($delivery->next_attempt_at);
    }

    private function deadLetter(TravelWebhookDelivery $delivery, string $reason): void
    {
        $delivery->forceFill([
            'status' => TravelWebhookDelivery::STATUS_FAILED,
            'next_attempt_at' => null,
        ])->save();

        logger()->warning('Webhook transporteur dead-letteré', [
            'delivery_id' => $delivery->id,
            'subscription_id' => $delivery->subscription_id,
            'reason' => $reason,
        ]);
    }
}
