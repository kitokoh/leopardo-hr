<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Contracts\DistanceCalculatorInterface;
use App\Modules\Geo\Domain\Contracts\NearestSearchInterface;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\Distance;
use App\Shared\Geo\GeoPoint;

/**
 * GEO-03 (#8352, BC-33 GEO) — implémentation de la façade transverse.
 *
 * Délègue au calculateur et au moteur « le plus proche » sélectionnés par le
 * provider (PostGIS si disponible, Haversine sinon) : les verticales ne
 * voient que GeoServiceContract et ignorent quel moteur produit les
 * résultats (GEO-04 : nearest + registry opt-in).
 */
final class GeoService implements GeoServiceContract
{
    public function __construct(
        private readonly DistanceCalculatorInterface $calculator,
        private readonly NearestSearchInterface $nearestSearch,
        private readonly SearchableRegistry $registry,
    ) {}

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

    public function registerSearchable(string $type, string $modelClass): void
    {
        $this->registry->register($type, $modelClass);
    }

    public function nearest(string $type, GeoPoint $center, ?float $radiusKm = null, ?int $limit = null): array
    {
        return $this->nearestSearch->nearest($type, $center, $radiusKm, $limit);
    }
}
