<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Modules\FarmOs\Domain\Enums\AreaSource;
use App\Modules\FarmOs\Domain\Enums\AreaUnit;
use App\Modules\FarmOs\Domain\Exceptions\FarmAreaMeasurementException;

/**
 * Farm OS — Garde d'honnêteté des superficies (ADR-FARM-0003).
 *
 * Règles appliquées côté serveur, pas seulement documentées :
 *   1. valeur et unité vont ensemble (jamais de nombre orphelin d'unité) ;
 *   2. `area_source = measured` est REFUSÉ sans géométrie réellement stockée ;
 *   3. par défaut, une superficie saisie est DECLARED — Farm OS ne se déclare
 *      jamais « mesurant » une donnée qu'un humain a tapée.
 */
final class FarmAreaGuard
{
    /**
     * @throws FarmAreaMeasurementException
     */
    public function assertConsistent(
        string|float|int|null $value,
        ?AreaUnit $unit,
        ?AreaSource $source,
        bool $hasGeometry,
    ): void {
        if (($value === null) !== ($unit === null)) {
            throw FarmAreaMeasurementException::inconsistentUnit(
                $value === null ? null : (string) $value,
                $unit?->value
            );
        }

        if ($source !== null && $source->requiresGeometry() && ! $hasGeometry) {
            throw FarmAreaMeasurementException::withoutGeometry();
        }
    }

    /**
     * Provenance retenue : la demande explicite de l'utilisateur, sinon
     * `declared` (jamais `measured` par déduction).
     */
    public function resolveSource(
        string|float|int|null $value,
        ?AreaSource $requested,
    ): ?AreaSource {
        if ($value === null) {
            return null;
        }

        return $requested ?? AreaSource::Declared;
    }

    /**
     * Conversion d'une superficie vers le mètre carré (pour comparaisons et
     * totaux) — arithmétique décimale, jamais de flottant binaire implicite.
     */
    public function toSquareMeters(string|float|int|null $value, ?AreaUnit $unit): ?string
    {
        if ($value === null || $unit === null) {
            return null;
        }

        /** @var numeric-string $amount */
        $amount = (string) $value;
        /** @var numeric-string $factor */
        $factor = $unit->squareMeterFactor();

        return bcmul($amount, $factor, 4);
    }
}
