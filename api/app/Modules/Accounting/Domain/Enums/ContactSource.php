<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Origine d'un contact comptable — COMPTABILITE_CONCEPTION.md §4.
 */
enum ContactSource: string
{
    case Manual = 'manual';
    case MarketingLead = 'marketing_lead';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }
}
