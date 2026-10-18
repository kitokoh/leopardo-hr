<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * TRADE-001 (#8476, BC-36 TRADE) — smoke test de la verticale Trade.
 *
 * Lecture pure derrière la gate `module.trade` : prouve que le feature flag
 * est actif et que le stack tenant canonique est traversé (pattern
 * VtcHealthController).
 */
final class TradeHealthController
{
    public function ping(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'module' => 'trade',
                'status' => 'ok',
            ],
        ]);
    }
}
