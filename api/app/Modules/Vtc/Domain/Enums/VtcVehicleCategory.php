<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Enums;

/**
 * Catégorie d'un véhicule VTC (BC-34 VTC, VTC-02/#8358) — berline, van ou
 * moto (spec §5.1). La catégorie pourra affiner le matching et la
 * tarification en évolution (hors scope v1).
 */
enum VtcVehicleCategory: string
{
    case Berline = 'berline';
    case Van = 'van';
    case Moto = 'moto';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $category): string => $category->value, self::cases());
    }
}
