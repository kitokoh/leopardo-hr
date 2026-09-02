<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantStockLevelRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantStockLevelRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantStockLevelResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-501 (#6200) — Niveaux de stock par (branche, ingrédient).
 *
 * Lecture pour tout employé du tenant ; écriture réservée à la gestion
 * (`principal`/`rh`). Toute résolution cross-tenant renvoie 404 (jamais 403).
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
            ->when($request->query('branch_id'), fn ($query, $branchId) => $query->where('branch_id', (int) $branchId))
            ->when($request->query('ingredient_id'), fn ($query, $ingredientId) => $query->where('ingredient_id', (int) $ingredientId))
            ->when($request->query('low_only') === 'true', fn ($query) => $query->whereNotNull('alert_threshold')->whereColumn('quantity', '<=', 'alert_threshold'))
            ->orderBy('ingredient_id')
            ->paginate($perPage);

        return RestaurantStockLevelResource::collection($levels)->response();
    }

    public function store(StoreRestaurantStockLevelRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantStockLevel::class)) {
            abort(403);
        }

        $level = RestaurantStockLevel::query()->create($request->validated());

        return (new RestaurantStockLevelResource($level))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantStockLevel $restaurantStockLevel): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantStockLevel->company_id) {
            abort(404);
        }

        return (new RestaurantStockLevelResource($restaurantStockLevel))->response();
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

        return (new RestaurantStockLevelResource($restaurantStockLevel))->response();
    }

    public function destroy(Request $request, RestaurantStockLevel $restaurantStockLevel): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantStockLevel->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantStockLevel)) {
            abort(403);
        }

        $restaurantStockLevel->delete();

        return new JsonResponse(null, 204);
    }
}
