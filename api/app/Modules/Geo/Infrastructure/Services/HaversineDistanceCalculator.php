<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Contracts\DistanceCalculatorInterface;
use App\Shared\Geo\Distance;
use App\Shared\Geo\GeoPoint;

/**
 * GEO-03 (#8352, BC-33 GEO) — calculateur de repli Haversine (PHP pur).
 *
 * Utilisé lorsque PostGIS est absent (environnement non provisionné, base de
 * test) : le mode dégradé ne casse jamais les verticales consommatrices
 * (décision D1 de la spec). Formule haversine sur sphère de rayon moyen
 * IUGG 6 371 000 m ; écart avec ST_Distance(geography) < 1 % sous 100 km.
 */
final class HaversineDistanceCalculator implements DistanceCalculatorInterface
{
    /** Rayon moyen de la Terre en mètres (IUGG). */
    private const EARTH_RADIUS_METERS = 6371000.0;

    public function distance(GeoPoint $from, GeoPoint $to): Distance
    {
        $latFrom = deg2rad($from->latitude);
        $latTo = deg2rad($to->latitude);
        $deltaLat = deg2rad($to->latitude - $from->latitude);
        $deltaLng = deg2rad($to->longitude - $from->longitude);

        $a = sin($deltaLat / 2.0) ** 2
            + cos($latFrom) * cos($latTo) * sin($deltaLng / 2.0) ** 2;

        // Garde anti-dérive flottante aux antipodes (a peut dépasser 1.0 de ε).
        $c = 2.0 * asin(min(1.0, sqrt($a)));

        return Distance::fromFloatMeters(self::EARTH_RADIUS_METERS * $c);
    }

    public function engine(): string
    {
        return 'haversine';
    }
}
