<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Unités de mesure FuelStation (issue #5797) — whitelist stricte.
 */
final class FuelUnit
{
    public const LITER = 'l';
    public const CUBIC_METER = 'm3';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::LITER, self::CUBIC_METER];
    }

    public static function isValid(string $unit): bool
    {
        return in_array($unit, self::values(), true);
    }
}
