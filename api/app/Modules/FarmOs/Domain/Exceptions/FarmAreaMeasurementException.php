<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Exceptions;

use RuntimeException;

/**
 * Farm OS — Superficie annoncée comme « mesurée » sans géométrie (ADR-FARM-0003).
 *
 * Farm OS ne prétend JAMAIS mesurer automatiquement une superficie : déclarer
 * `area_source = measured` exige une géométrie réellement présente.
 */
class FarmAreaMeasurementException extends RuntimeException
{
    public static function withoutGeometry(): self
    {
        return new self('A measured area requires a stored geometry: area_source=measured without geometry is refused.');
    }

    public static function inconsistentUnit(?string $value, ?string $unit): self
    {
        return new self(sprintf(
            'Area value and area unit must be provided together (value=%s, unit=%s).',
            $value ?? 'null',
            $unit ?? 'null'
        ));
    }
}
