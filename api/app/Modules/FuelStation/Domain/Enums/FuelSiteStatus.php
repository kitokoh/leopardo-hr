<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Statut d'un site opérationnel — Issue #5796 (FUEL-002).
 */
enum FuelSiteStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
