<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Controllers;

use App\Modules\Geo\Infrastructure\Services\GeoCapabilities;
use App\Shared\Contracts\Geo\GeoServiceContract;
use Illuminate\Http\JsonResponse;

/**
 * Diagnostic des capacités géospatiales (GEO-05, issue #8354, BC-33 GEO).
 *
 * `GET /v1/geo/capabilities` (réservé admin tenant, garde `geo.admin`) →
 * état de l'extension PostGIS et moteur de distance actif. Endpoint de
 * supervision opérationnelle : il permet de vérifier qu'un environnement
 * est bien provisionné (runbook Neon, GEO-01) sans accès base direct.
 * Aucune donnée métier tenant n'est exposée.
 */
final class GeoCapabilitiesController
{
    public function __construct(
        private readonly GeoCapabilities $capabilities,
        private readonly GeoServiceContract $geo,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'postgis_available' => $this->capabilities->postgisAvailable(),
                'postgis_version' => $this->capabilities->postgisVersion(),
                'distance_engine' => $this->geo->distanceEngine(),
            ],
        ]);
    }
}
