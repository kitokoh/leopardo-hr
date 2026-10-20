<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Enums;

/**
 * Mode de calcul d'une regle de commission (BC-38, #8687).
 */
enum FeeMode: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case Mixed = 'mixed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
