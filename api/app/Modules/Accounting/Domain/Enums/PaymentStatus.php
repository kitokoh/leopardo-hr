<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Statut d'un paiement/rapprochement — COMPTABILITE_CONCEPTION.md §4.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Recorded = 'recorded';
    case Matched = 'matched';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
