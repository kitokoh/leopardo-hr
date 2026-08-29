<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Point de contrôle de la verticale TravelAgency (TRAVEL-101/#5977).
 *
 * Route de smoke test `GET /api/v1/travel/ping` : prouve que le module est
 * chargé, que le feature flag `travelagency` est actif pour le tenant courant
 * et que le pipeline middleware (auth → tenant → module) est opérationnel.
 * Aucune donnée métier — lecture pure, sans effet de bord.
 */
final class TravelHealthController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'travelagency',
        ]);
    }
}
