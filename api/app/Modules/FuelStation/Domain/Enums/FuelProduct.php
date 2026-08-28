<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Enums;

/**
 * Produits pétroliers FuelStation (issue #5797) — whitelist stricte.
 */
final class FuelProduct
{
    public const DIESEL = 'diesel';
    public const ESSENCE_95 = 'essence_95';
    public const ESSENCE_98 = 'essence_98';
    public const GPL = 'gpl';
    public const LUBRIFIANT = 'lubrifiant';
    public const AUTRE = 'autre';

    /** @return array<int, string> */
    public static function values(): array
    {
        return [self::DIESEL, self::ESSENCE_95, self::ESSENCE_98, self::GPL, self::LUBRIFIANT, self::AUTRE];
    }

    public static function isValid(string $product): bool
    {
        return in_array($product, self::values(), true);
    }
}
