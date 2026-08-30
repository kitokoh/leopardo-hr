<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookDelivery;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;
use App\Modules\TravelAgency\Infrastructure\Jobs\DeliverTravelWebhookJob;
use Illuminate\Support\Facades\DB;

/**
 * TRAVEL-806 (#6097) — Consommateur d'outbox → livraisons webhooks
 * transporteurs.
 *
 * Route les événements métier (réservation confirmée/annulée, billet émis,
 * paiement) vers les abonnements webhook du transporteur du trajet, dans
 * une transaction (échec → retry outbox). Idempotent : la contrainte
 * unique `(subscription_id, event_id)` interdit les doublons de livraison
 * sur rejeu.
 */
final class TravelWebhookConsumer implements TravelOutboxConsumer
{
    private const BOOKING_EVENTS = [
        'travel.booking.confirmed.v1',
        'travel.booking.cancelled.v1',
        'travel.booking.pending.v1',
        'travel.booking.expired.v1',
        'travel.ticket.issued.v1',
        'travel.payment.confirmed.v1',
        'travel.payment.refunded.v1',
    ];

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::BOOKING_EVENTS, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $companyId = (string) ($payload['company_id'] ?? '');
        $tripId = $this->tripIdFor($payload);

        if ($companyId === '' || $tripId === null) {
            return; // Pas de trajet associé (contact, …) → rien à livrer.
        }

        $carrierId = TravelTrip::query()
            ->where('company_id', $companyId)
            ->whereKey($tripId)
            ->value('carrier_id');

        if ($carrierId === null) {
            return; // Trajet sans transporteur affecté.
        }

        $subscriptions = TravelWebhookSubscription::query()
            ->where('company_id', $companyId)
            ->where('carrier_id', $carrierId)
            ->where('active', true)
            ->get()
            ->filter(fn (TravelWebhookSubscription $s): bool => $s->subscribesTo((string) $payload['event_type']));

        foreach ($subscriptions as $subscription) {
            DB::transaction(function () use ($subscription, $companyId, $payload): void {
                $created = TravelWebhookDelivery::query()->firstOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'event_id' => (int) ($payload['event_id'] ?? 0),
                    ],
                    [
                        'company_id' => $companyId,
                        'event_type' => (string) $payload['event_type'],
                        'payload_redacted' => $payload,
                        'status' => TravelWebhookDelivery::STATUS_PENDING,
                        'attempts' => 0,
                        'next_attempt_at' => now(),
                    ],
                );

                if ($created->wasRecentlyCreated) {
                    DeliverTravelWebhookJob::dispatch($created->id, $subscription->id);
                }
            });
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function tripIdFor(array $payload): ?int
    {
        if (isset($payload['trip_id']) && is_numeric($payload['trip_id'])) {
            return (int) $payload['trip_id'];
        }

        if (isset($payload['booking_reference'])) {
            $booking = TravelBooking::query()
                ->where('reference', (string) $payload['booking_reference'])
                ->first();

            return $booking?->trip_id !== null ? (int) $booking->trip_id : null;
        }

        return null;
    }
}
