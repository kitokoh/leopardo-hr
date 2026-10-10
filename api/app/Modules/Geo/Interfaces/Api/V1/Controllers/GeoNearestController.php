<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Controllers;

use App\Modules\Geo\Interfaces\Api\V1\Requests\NearestRequest;
use App\Modules\Geo\Interfaces\Api\V1\Resources\NearestResultResource;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Recherche « les plus proches » (GEO-05, issue #8354, BC-33 GEO).
 *
 * `GET /v1/geo/nearest?type=&lat=&lng=&radius_km=&limit=` → liste triée par
 * distance croissante, limitée aux types ENREGISTRÉS dans la registry
 * opt-in `geo.searchables` (fail-closed : un type inconnu lève
 * UnknownSearchableTypeException → 422 GEO_UNKNOWN_SEARCHABLE_TYPE, aucune
 * donnée tenant n'est exposée sans enregistrement explicite, spec §7).
 */
final class GeoNearestController
{
    public function __construct(
        private readonly GeoServiceContract $geo,
    ) {}

    public function index(NearestRequest $request): AnonymousResourceCollection
    {
        /** @var array{type: string, lat: numeric-string, lng: numeric-string, radius_km?: numeric-string|null, limit?: int|null} $validated */
        $validated = $request->validated();

        $center = new GeoPoint((float) $validated['lat'], (float) $validated['lng']);

        $results = $this->geo->nearest(
            $validated['type'],
            $center,
            isset($validated['radius_km']) ? (float) $validated['radius_km'] : null,
            $validated['limit'] ?? null,
        );

        return NearestResultResource::collection(collect($results));
    }
}
