<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Controllers;

use App\Modules\Geo\Interfaces\Api\V1\Requests\DistanceRequest;
use App\Shared\Contracts\Geo\GeoServiceContract;
use App\Shared\Geo\GeoPoint;
use Illuminate\Http\JsonResponse;

/**
 * Calcul de distance (GEO-05, issue #8354, BC-33 GEO).
 *
 * `POST /v1/geo/distance` `{from:{lat,lng}, to:{lat,lng}}` →
 * `{data:{distance_m}}`. Le calcul est délégué à la façade transverse
 * (PostGIS si disponible, Haversine sinon — le choix du moteur est
 * invisible de l'appelant, décision D1 de la spec).
 */
final class GeoDistanceController
{
    public function __construct(
        private readonly GeoServiceContract $geo,
    ) {}

    public function __invoke(DistanceRequest $request): JsonResponse
    {
        /** @var array{from: array<string, mixed>, to: array<string, mixed>} $validated */
        $validated = $request->validated();

        $distance = $this->geo->distance(
            GeoPoint::fromArray($validated['from']),
            GeoPoint::fromArray($validated['to']),
        );

        return response()->json([
            'data' => [
                'distance_m' => $distance->meters(),
            ],
        ]);
    }
}
