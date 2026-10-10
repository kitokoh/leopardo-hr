<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use App\Modules\Vtc\Application\Actions\EstimateRideFareAction;
use App\Modules\Vtc\Interfaces\Api\V1\Requests\RideEstimateRequest;
use App\Shared\Geo\GeoPoint;
use Illuminate\Http\JsonResponse;

/**
 * Devis d'une course (BC-34 VTC, VTC-03/#8359).
 *
 * `POST /v1/vtc/rides/estimate` : distance via le core géospatial (BC-33,
 * jamais de calcul local), coefficient routier `vtc.pricing.road_factor`,
 * grille `vtc_fare_profiles` (demandée ou par défaut) → devis normalisé.
 */
final class VtcRideEstimateController
{
    public function __construct(
        private readonly EstimateRideFareAction $estimateFare,
    ) {
    }

    public function __invoke(RideEstimateRequest $request): JsonResponse
    {
        /** @var array{pickup: array<string, mixed>, dropoff: array<string, mixed>, fare_profile_id?: int|null} $validated */
        $validated = $request->validated();

        $estimate = $this->estimateFare->execute(
            GeoPoint::fromArray($validated['pickup']),
            GeoPoint::fromArray($validated['dropoff']),
            $validated['fare_profile_id'] ?? null,
        );

        return response()->json(['data' => $estimate->toArray()]);
    }
}
