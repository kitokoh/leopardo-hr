<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * TRAVEL-806 (#6097) — Événements livrables aux webhooks transporteurs.
 *
 * Sous-ensemble des événements d'outbox TravelAgency pertinents pour les
 * partenaires (réservations, annulations, billets, trajets).
 */
enum TravelWebhookEvent: string
{
    case BOOKING_PENDING = 'travel.booking.pending.v1';
    case BOOKING_CONFIRMED = 'travel.booking.confirmed.v1';
    case BOOKING_CANCELLED = 'travel.booking.cancelled.v1';
    case PAYMENT_REFUNDED = 'travel.payment.refunded.v1';
    case TICKET_ISSUED = 'travel.ticket.issued.v1';
    case TRIP_PUBLISHED = 'travel.trip.published.v1';
    case TRIP_CANCELLED = 'travel.trip.cancelled.v1';

    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
