<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Point de contrôle de la verticale RestaurantManager (RESTO-101/#6158).
 *
 * Route de smoke test `GET /api/v1/restaurant/ping` : prouve que le module
 * est chargé, que le feature flag `restaurantmanager` est actif pour le
 * tenant courant et que le pipeline middleware (auth → tenant → module)
 * est opérationnel. Aucune donnée métier — lecture pure, sans effet de bord.
 */
final class RestaurantHealthController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'restaurantmanager',
        ]);
    }
}
