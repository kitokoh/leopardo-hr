<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Enums;

/**
 * Type d'événement de tracking (BC-26 DELIVERY, DELIVERY-103/#6284).
 *
 * Les événements sont idempotents (clé (company_id, delivery_id, type,
 * event_at) ou idempotency_key client) — un rejeu ne duplique jamais le suivi.
 */
enum DeliveryEventType: string
{
    case PickedUp = 'picked_up';
    case OutForDelivery = 'out_for_delivery';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
