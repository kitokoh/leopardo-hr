<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Statut d'une ligne de relevé bancaire — rapprochement bancaire #5435.
 */
enum BankStatementLineStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
