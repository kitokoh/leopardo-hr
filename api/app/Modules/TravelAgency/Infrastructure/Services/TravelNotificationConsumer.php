<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Contracts\TravelOutboxConsumer;

/**
 * TRAVEL-415 (#6067) — Consommateur d'outbox → notifications voyageur.
 *
 * Route les événements métier vers `TravelNotificationService` : le canal
 * n'est activé que s'il est configuré ET couvert par un consentement actif
 * (RGPD). Idempotent par construction : les envois réels sont journalisés,
 * un rejeu d'événement produit de nouvelles tentatives tracées mais jamais
 * d'envoi sans consentement.
 */
final class TravelNotificationConsumer implements TravelOutboxConsumer
{
    private const EVENTS = [
        'travel.booking.confirmed.v1',
        'travel.booking.cancelled.v1',
        'travel.booking.expired.v1',
        'travel.payment.confirmed.v1',
        'travel.payment.refunded.v1',
        'travel.ticket.issued.v1',
        'travel.trip.cancelled.v1',
    ];

    public function __construct(private readonly TravelNotificationService $notifications) {}

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::EVENTS, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $companyId = isset($payload['company_id']) ? (string) $payload['company_id'] : '';
        $eventType = isset($payload['event_type']) ? (string) $payload['event_type'] : '';

        if ($companyId === '' || $eventType === '') {
            return;
        }

        $this->notifications->notify($companyId, $eventType, $payload);
    }
}
