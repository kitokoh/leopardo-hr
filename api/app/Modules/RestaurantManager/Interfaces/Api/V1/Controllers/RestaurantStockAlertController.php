<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantStockAlertService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantStockLevelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-505 (#6204) — Alertes de seuil de stock.
 *
 * `GET /restaurant/stock/alerts` : liste lecture des niveaux sous le seuil
 * (le job `leopardo:restaurant:stock-alerts` publie l'événement outbox
 * `restaurant.stock.alert.v1` — une alerte par ingrédient/branche/jour).
 */
class RestaurantStockAlertController extends Controller
{
    public function __construct(
        private readonly RestaurantStockAlertService $alerts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantStockLevel::class)) {
            abort(403);
        }

        $branchParam = $request->query('branch_id');
        $branchId = is_numeric($branchParam) ? (int) $branchParam : null;

        // #7599 — les alertes de stock sont bornées aux succursales accessibles.
        $accessible = $actor->accessibleResourceIds('restaurant_branch');
        if ($accessible !== null) {
            if ($branchId !== null && ! in_array($branchId, $accessible, true)) {
                abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
            }
            if ($branchId === null && $accessible === []) {
                return RestaurantStockLevelResource::collection(collect())->response();
            }
        }

        $levels = $this->alerts->belowThreshold($actor->company_id, $branchId);

        if ($accessible !== null && $branchId === null) {
            $levels = $levels->filter(
                fn (RestaurantStockLevel $level): bool => in_array((int) $level->branch_id, $accessible, true)
            )->values();
        }

        return RestaurantStockLevelResource::collection($levels)->response();
    }
}
