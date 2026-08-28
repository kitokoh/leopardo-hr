<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Statuts FuelStation (issue #5797) — whitelist stricte.
 */
final class FuelStatus
{
    public const ACTIVE = 'active';
    public const MAINTENANCE = 'maintenance';
    public const RETIRED = 'retired';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::ACTIVE, self::MAINTENANCE, self::RETIRED];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::values(), true);
    }
}
