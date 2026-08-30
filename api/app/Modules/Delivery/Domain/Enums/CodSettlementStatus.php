<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Enums;

/**
 * Statut d'un règlement COD (contre-remboursement) (BC-26 DELIVERY,
 * DELIVERY-103/#6284). Le posting comptable (BC-08) est idempotent.
 */
enum CodSettlementStatus: string
{
    case Pending = 'pending';
    case Collected = 'collected';
    case Settled = 'settled';
    case Reconciled = 'reconciled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
