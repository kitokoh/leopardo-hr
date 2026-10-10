<?php

declare(strict_types=1);

namespace App\Modules\Geo\Interfaces\Api\V1\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Point de contrôle du module Geo (GEO-02/#8351, BC-33 GEO).
 *
 * Route de smoke test `GET /api/v1/geo/ping` : prouve que le module est
 * chargé, que le feature flag `geo` est actif pour le tenant courant et que
 * le pipeline middleware (auth → tenant → module) est opérationnel. Aucune
 * donnée métier — lecture pure, sans effet de bord.
 */
final class GeoHealthController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'geo',
        ]);
    }
}
