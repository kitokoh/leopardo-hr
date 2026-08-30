<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Enums;

/**
 * Statut d'une tournée (BC-26 DELIVERY, DELIVERY-103/#6284).
 */
enum RouteStatus: string
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
