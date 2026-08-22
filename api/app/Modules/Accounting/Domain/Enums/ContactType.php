<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Type de tiers de facturation — COMPTABILITE_CONCEPTION.md §4.
 */
enum ContactType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Both = 'both';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
