<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Infrastructure\Services\Mobile\RestaurantMobileManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-803 (#6224) — API mobile gérant (KPIs, alertes stock, clôtures).
 *
 * Authentifiée Sanctum, tenant-scope. Les indicateurs sont calculés côté
 * serveur ; la clôture de caisse délègue à ClosePosSessionAction (écart
 * calculé serveur, événement restaurant.pos.closed.v1).
 */
class RestaurantMobileManagerController extends Controller
{
    public function __construct(private readonly RestaurantMobileManagerService $service)
    {
    }

    public function kpis(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $this->service->kpis($actor)]);
    }

    public function stockAlerts(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return response()->json(['data' => $this->service->stockAlerts($actor)]);
    }

    public function currentPosSession(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $session = $this->service->currentPosSession($actor);

        if (! $session instanceof RestaurantPosSession) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => [
            'id' => $session->id,
            'branch_id' => $session->branch_id,
            'status' => $session->status->value,
            'opened_at' => $session->opened_at?->toIso8601String(),
            'opening_cash_minor' => $session->opening_cash_minor,
        ]]);
    }

    public function closePosSession(Request $request, RestaurantPosSession $restaurantPosSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $data = $request->validate([
            'counted_cash_minor' => ['required', 'integer', 'min:0'],
            'variance_reason' => ['sometimes', 'string', 'max:500'],
        ]);

        $session = $this->service->closePosSession($actor, $restaurantPosSession, $data);

        return response()->json(['data' => [
            'id' => $session->id,
            'status' => $session->status->value,
            'closed_at' => $session->closed_at?->toIso8601String(),
            'expected_cash_minor' => $session->expected_cash_minor,
            'counted_cash_minor' => $session->counted_cash_minor,
            'variance_minor' => $session->variance_minor,
        ]]);
    }
}
