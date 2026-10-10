<?php

declare(strict_types=1);

namespace App\Modules\Geo\Infrastructure\Services;

use App\Modules\Geo\Domain\Contracts\DistanceCalculatorInterface;
use App\Shared\Geo\Distance;
use App\Shared\Geo\GeoPoint;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * GEO-03 (#8352, BC-33 GEO) — calculateur de référence PostGIS.
 *
 * ST_Distance sur `geography(Point, 4326)` (ellipsoïde WGS 84) — précision
 * de référence du projet. Bindings paramétrés exclusivement (jamais
 * d'interpolation de coordonnées dans le SQL, règle spec §7).
 *
 * Sélectionné par le provider uniquement lorsque GeoCapabilities confirme
 * l'extension ; en l'absence de PostGIS, HaversineDistanceCalculator prend
 * le relais — ce calculateur n'est alors jamais résolu.
 */
final class PostgisDistanceCalculator implements DistanceCalculatorInterface
{
    public function distance(GeoPoint $from, GeoPoint $to): Distance
    {
        $row = DB::selectOne(
            'SELECT ST_Distance(
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
            ) AS distance_m',
            [$from->longitude, $from->latitude, $to->longitude, $to->latitude]
        );

        if (! is_object($row) || ! property_exists($row, 'distance_m') || ! is_numeric($row->distance_m)) {
            throw new RuntimeException('PostGIS ST_Distance : résultat illisible.');
        }

        return Distance::fromFloatMeters((float) $row->distance_m);
    }

    public function engine(): string
    {
        return 'postgis';
    }
}
