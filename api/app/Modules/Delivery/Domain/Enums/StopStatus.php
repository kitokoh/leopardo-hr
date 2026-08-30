<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Enums;

/**
 * Statut d'un arrêt de tournée (BC-26 DELIVERY, DELIVERY-103/#6284).
 */
enum StopStatus: string
{
    case Pending = 'pending';
    case EnRoute = 'en_route';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Skipped = 'skipped';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
