<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Types d'équipements FuelStation (issue #5797) — whitelist stricte.
 */
final class FuelEquipmentType
{
    public const PUMP = 'pump';
    public const TANK = 'tank';
    public const METER = 'meter';
    public const NOZZLE = 'nozzle';
    public const OTHER = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::PUMP, self::TANK, self::METER, self::NOZZLE, self::OTHER];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::values(), true);
    }
}
