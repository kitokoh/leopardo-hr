<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Enums;

/**
 * Statut d'une livraison (BC-26 DELIVERY, DELIVERY-103/#6284).
 *
 * Cycle de vie : created → assigned → picked_up → out_for_delivery → arrived
 * → delivered | failed → returned | cancelled. Les transitions sont
 * verrouillées par DeliveryStateMachine (états terminaux : delivered,
 * returned, cancelled — aucune réouverture).
 *
 * @see \App\Modules\Delivery\Domain\Support\DeliveryStateMachine
 */
enum DeliveryStatus: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case OutForDelivery = 'out_for_delivery';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
