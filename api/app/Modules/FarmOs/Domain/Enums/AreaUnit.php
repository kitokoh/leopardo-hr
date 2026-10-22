<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS (BC-37 AGRICULTURE) — Unité de superficie.
 *
 * Sous-ensemble volontairement minimal des unités réellement utilisées par les
 * exploitations d'Afrique, d'Europe et de Turquie : hectare, mètre carré, acre,
 * are. Toute conversion se fait par {@see toSquareMeters()} — jamais par une
 * constante flottante dispersée dans le code.
 */
enum AreaUnit: string
{
    case Hectare = 'ha';
    case SquareMeter = 'm2';
    case Acre = 'acre';
    case Are = 'are';

    public function translationKey(): string
    {
        return 'farm.area_unit.'.$this->value;
    }

    /**
     * Facteur de conversion vers le mètre carré.
     */
    public function squareMeterFactor(): string
    {
        return match ($this) {
            self::Hectare => '10000',
            self::SquareMeter => '1',
            self::Acre => '4046.8564224',
            self::Are => '100',
        };
    }

    public function isMetric(): bool
    {
        return $this !== self::Acre;
    }
}
