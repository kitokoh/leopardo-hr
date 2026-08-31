<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantTableRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantTableRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantTableResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-301 (#6182) — CRUD des tables du plan de salle (référentiel restaurant).
 *
 * Toute résolution d'un `{restaurantTable}` d'un autre tenant renvoie 404
 * (jamais 403, qui révélerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantTablePolicy`.
 */
class RestaurantTableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantTable::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $tables = RestaurantTable::query()
            ->orderBy('label')
            ->paginate($perPage);

        return RestaurantTableResource::collection($tables)->response();
    }

    public function store(StoreRestaurantTableRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantTable::class)) {
            abort(403);
        }

        $table = RestaurantTable::query()->create($request->validated());

        return (new RestaurantTableResource($table))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantTable $restaurantTable): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantTable->company_id) {
            abort(404);
        }

        return (new RestaurantTableResource($restaurantTable))->response();
    }

    public function update(UpdateRestaurantTableRequest $request, RestaurantTable $restaurantTable): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantTable->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantTable)) {
            abort(403);
        }

        $restaurantTable->update($request->validated());

        return (new RestaurantTableResource($restaurantTable))->response();
    }

    public function destroy(Request $request, RestaurantTable $restaurantTable): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantTable->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantTable)) {
            abort(403);
        }

        $restaurantTable->delete();

        return new JsonResponse(null, 204);
    }
}
