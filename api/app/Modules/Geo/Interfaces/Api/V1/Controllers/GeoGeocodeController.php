<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Controllers;

use App\Modules\Geo\Infrastructure\Services\GeocodingService;
use App\Modules\Geo\Interfaces\Api\V1\Requests\GeocodeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Géocodage adresse → coordonnées (GEO-08, issue #8466, BC-34 GEO).
 *
 * `POST /v1/geo/geocode` `{address, country_code?}` →
 * `{data:{latitude, longitude, provider, cached}}`.
 *
 * Fail-closed : sans fournisseur configuré, 503 `geocoding_disabled` — le
 * core ne fabrique jamais de coordonnées. Adresse non résolue → 404
 * `address_not_found` (distinct d'une erreur de validation, 422).
 */
final class GeoGeocodeController
{
    public function __construct(
        private readonly GeocodingService $geocoding,
    ) {}

    public function __invoke(GeocodeRequest $request): JsonResponse
    {
        if (! $this->geocoding->isEnabled()) {
            return response()->json([
                'error' => [
                    'code' => 'geocoding_disabled',
                    'message' => 'Le géocodage n\'est pas activé sur cette instance.',
                ],
            ], 503);
        }

        /** @var array{address: string, country_code?: string|null} $validated */
        $validated = $request->validated();

        $result = $this->geocoding->geocode(
            $validated['address'],
            $validated['country_code'] ?? null,
        );

        if ($result === null) {
            return response()->json([
                'error' => [
                    'code' => 'address_not_found',
                    'message' => 'Adresse non résolue.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'latitude' => $result['point']->latitude,
                'longitude' => $result['point']->longitude,
                'provider' => $this->geocoding->providerName(),
                'cached' => $result['cached'],
            ],
        ]);
    }
}
