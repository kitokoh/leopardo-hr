<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Type d'équipement FuelStation — Issue #5797 (FUEL-003).
 */
enum FuelEquipmentType: string
{
    case Pump = 'pump';
    case Tank = 'tank';
    case Meter = 'meter';
    case Nozzle = 'nozzle';
    case Console = 'console';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
