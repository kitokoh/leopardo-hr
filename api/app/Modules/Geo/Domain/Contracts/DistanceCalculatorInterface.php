<?php

declare(strict_types=1);

namespace App\Modules\Geo\Domain\Contracts;

use App\Shared\Geo\Distance;
use App\Shared\Geo\GeoPoint;

/**
 * GEO-03 (#8352, BC-33 GEO) — calculateur de distance entre deux points.
 *
 * Deux implémentations interchangeables, sélectionnées par le provider via
 * GeoCapabilities : PostgisDistanceCalculator (ST_Distance sur geography,
 * précision de référence) et HaversineDistanceCalculator (fallback portable
 * sans extension — écart < 1 % sous 100 km, tolérance testée).
 *
 * Les verticales ne résolvent JAMAIS ce contrat directement : elles passent
 * par la façade transverse App\Shared\Contracts\Geo\GeoServiceContract.
 */
interface DistanceCalculatorInterface
{
    /** Distance géodésique « à vol d'oiseau » entre les deux points. */
    public function distance(GeoPoint $from, GeoPoint $to): Distance;

    /** Identifiant du moteur (`postgis` | `haversine`) — diagnostic et tests. */
    public function engine(): string;
}
