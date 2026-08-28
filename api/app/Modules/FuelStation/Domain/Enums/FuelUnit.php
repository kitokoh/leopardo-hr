<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Unités de mesure FuelStation — Issue #5797 (FUEL-003).
 */
enum FuelUnit: string
{
    case Liter = 'liter';
    case Kilogram = 'kg';
    case Unit = 'unit';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $unit): string => $unit->value, self::cases());
    }
}
