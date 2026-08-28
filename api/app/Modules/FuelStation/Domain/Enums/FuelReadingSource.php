<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Sources de relevé de compteur FuelStation (issue #5798) — whitelist.
 */
final class FuelReadingSource
{
    public const MANUAL = 'manual';

    public const API = 'api';

    public const DEVICE = 'device';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::MANUAL, self::API, self::DEVICE];
    }

    public static function isValid(string $source): bool
    {
        return in_array($source, self::values(), true);
    }
}
