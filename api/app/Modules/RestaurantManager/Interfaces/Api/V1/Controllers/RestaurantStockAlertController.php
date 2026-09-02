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
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantStockLevel::class)) {
            abort(403);
        }

        $branchId = $request->query('branch_id') !== null ? (int) $request->query('branch_id') : null;

        $levels = $this->alerts->belowThreshold($actor->company_id, $branchId);

        return RestaurantStockLevelResource::collection($levels)->response();
    }
}
