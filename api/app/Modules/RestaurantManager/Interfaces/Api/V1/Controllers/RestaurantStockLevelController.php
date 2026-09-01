<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantStockLevelRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantStockLevelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-501 (#6200) — Niveaux de stock (lecture + seuils).
 *
 * La quantité courante n'est JAMAIS écrite directement : elle ne change que
 * par les mouvements (StockMovementService — ventes, réceptions, inventaires,
 * ajustements). Cet écran expose les niveaux et permet de piloter les seuils
 * d'alerte (`reorder_level`, `alert_threshold`, RESTO-505) et le coût moyen.
 */
class RestaurantStockLevelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantStockLevel::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $levels = RestaurantStockLevel::query()
            ->with('ingredient')
            ->when($request->has('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->query('branch_id')))
            ->when($request->has('ingredient_id'), fn ($query) => $query->where('ingredient_id', (int) $request->query('ingredient_id')))
            ->orderBy('ingredient_id')
            ->paginate($perPage);

        return RestaurantStockLevelResource::collection($levels)->response();
    }

    public function update(UpdateRestaurantStockLevelRequest $request, RestaurantStockLevel $restaurantStockLevel): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantStockLevel->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantStockLevel)) {
            abort(403);
        }

        $restaurantStockLevel->update($request->validated());

        return (new RestaurantStockLevelResource($restaurantStockLevel->load('ingredient')))->response();
    }
}
