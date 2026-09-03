<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Point de contrôle du module Delivery (DELIVERY-101/#6282).
 *
 * Route de smoke test `GET /api/v1/delivery/ping` : prouve que le module est
 * chargé, que le feature flag `delivery` est actif pour le tenant courant et
 * que le pipeline middleware (auth → tenant → module) est opérationnel.
 * Aucune donnée métier — lecture pure, sans effet de bord.
 */
final class DeliveryHealthController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'delivery',
        ]);
    }
}
