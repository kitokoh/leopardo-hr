<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Interfaces\Api\V1\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Point de contrôle de la verticale VTC (VTC-01/#8357, BC-34 VTC).
 *
 * Route de smoke test `GET /api/v1/vtc/ping` : prouve que le module est
 * chargé, que le feature flag `vtc` est actif pour le tenant courant et que
 * le pipeline middleware (auth → tenant → module) est opérationnel. Aucune
 * donnée métier — lecture pure, sans effet de bord.
 */
final class VtcHealthController
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'vtc',
        ]);
    }
}
