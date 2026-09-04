<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantUnit;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantUnitRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantUnitRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantUnitResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-303 (#6184) — CRUD des unités de mesure du référentiel restaurant.
 *
 * Toute résolution d'un `{restaurantUnit}` d'un autre tenant renvoie 404
 * (jamais 403, qui révélerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantUnitPolicy`.
 */
class RestaurantUnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantUnit::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $units = RestaurantUnit::query()
            ->orderBy('code')
            ->paginate($perPage);

        return RestaurantUnitResource::collection($units)->response();
    }

    public function store(StoreRestaurantUnitRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantUnit::class)) {
            abort(403);
        }

        $unit = RestaurantUnit::query()->create($request->validated());

        return (new RestaurantUnitResource($unit))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantUnit $restaurantUnit): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantUnit->company_id) {
            abort(404);
        }

        return (new RestaurantUnitResource($restaurantUnit))->response();
    }

    public function update(UpdateRestaurantUnitRequest $request, RestaurantUnit $restaurantUnit): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantUnit->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantUnit)) {
            abort(403);
        }

        $restaurantUnit->update($request->validated());

        return (new RestaurantUnitResource($restaurantUnit))->response();
    }

    public function destroy(Request $request, RestaurantUnit $restaurantUnit): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantUnit->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantUnit)) {
            abort(403);
        }

        $restaurantUnit->delete();

        return new JsonResponse(null, 204);
    }
}
