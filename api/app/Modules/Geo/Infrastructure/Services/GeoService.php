<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Contracts\DistanceCalculatorInterface;
use App\Shared\Geo\Distance;
use App\Shared\Geo\GeoPoint;
use App\Shared\Contracts\Geo\GeoServiceContract;

/**
 * GEO-03 (#8352, BC-33 GEO) — implémentation de la façade transverse.
 *
 * Délègue au calculateur sélectionné par le provider (PostGIS si disponible,
 * Haversine sinon) : les verticales ne voient que GeoServiceContract et
 * ignorent quel moteur produit les distances.
 */
final class GeoService implements GeoServiceContract
{
    public function __construct(
        private readonly DistanceCalculatorInterface $calculator,
    ) {
    }

    public function distanceMeters(GeoPoint $from, GeoPoint $to): int
    {
        return $this->calculator->distance($from, $to)->meters();
    }

    public function distance(GeoPoint $from, GeoPoint $to): Distance
    {
        return $this->calculator->distance($from, $to);
    }

    public function distanceEngine(): string
    {
        return $this->calculator->engine();
    }
}
