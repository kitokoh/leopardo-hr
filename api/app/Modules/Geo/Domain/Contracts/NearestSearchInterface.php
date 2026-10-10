<?php

declare(strict_types=1);

namespace App\Modules\Geo\Domain\Contracts;

use App\Shared\Geo\GeoPoint;
use App\Shared\Geo\NearestResult;

/**
 * GEO-04 (#8353, BC-33 GEO) — moteur de recherche « le plus proche ».
 *
 * Implémentation unique : EloquentNearestSearch (ST_DWithin + tri KNN
 * PostGIS si disponible, repli Haversine SQL avec pré-filtre bounding-box).
 * Les verticales ne résolvent jamais ce contrat directement : elles passent
 * par la façade App\Shared\Contracts\Geo\GeoServiceContract.
 */
interface NearestSearchInterface
{
    /**
     * Entités du type enregistré, dans le rayon, triées par distance
     * croissante (une seule requête — aucun N+1).
     *
     * @return list<NearestResult>
     *
     * @throws \App\Modules\Geo\Domain\Exceptions\UnknownSearchableTypeException
     */
    public function nearest(string $type, GeoPoint $center, ?float $radiusKm = null, ?int $limit = null): array;
}
