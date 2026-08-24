<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Cycle de vie d'un ordre de virement salarial — issue #5239 (Phase C).
 */
enum PaymentOrderStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Executed = 'executed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
