<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Enums;

/**
 * Types d'événements du journal append-only `vtc_ride_events` (BC-34 VTC,
 * VTC-02/#8358). Une entrée par transition de la state machine et par étape
 * du dispatch (offre, déclinaison, timeout) — audit complet du cycle de vie
 * (spec §5.2, VTC-04).
 */
enum VtcRideEventType: string
{
    case RideRequested = 'ride.requested';
    case DispatchStarted = 'dispatch.started';
    case OfferSent = 'dispatch.offer_sent';
    case OfferDeclined = 'dispatch.offer_declined';
    case OfferExpired = 'dispatch.offer_expired';
    case RideAccepted = 'ride.accepted';
    case DriverArrived = 'ride.driver_arrived';
    case RideStarted = 'ride.started';
    case RideCompleted = 'ride.completed';
    case RideCancelled = 'ride.cancelled';
    case DispatchExhausted = 'dispatch.exhausted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
