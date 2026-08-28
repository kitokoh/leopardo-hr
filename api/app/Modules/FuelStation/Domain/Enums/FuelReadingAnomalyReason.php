<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Raison d'anomalie d'un relevé FuelStation — Issue #5798 (FUEL-004).
 */
enum FuelReadingAnomalyReason: string
{
    case DecreasingValue = 'decreasing_value';
    case MeterReplaced = 'meter_replaced';
    case OutOfRange = 'out_of_range';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $reason): string => $reason->value, self::cases());
    }
}
