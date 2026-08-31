<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantCategory;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantCategoryRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantCategoryRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-302 (#6183) — CRUD des catégories de produits du référentiel restaurant.
 *
 * Toute résolution d'un `{restaurantCategory}` d'un autre tenant renvoie 404
 * (jamais 403, qui révélerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantCategoryPolicy`.
 */
class RestaurantCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantCategory::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $categories = RestaurantCategory::query()
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantCategoryResource::collection($categories)->response();
    }

    public function store(StoreRestaurantCategoryRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantCategory::class)) {
            abort(403);
        }

        $category = RestaurantCategory::query()->create($request->validated());

        return (new RestaurantCategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantCategory $restaurantCategory): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantCategory->company_id) {
            abort(404);
        }

        return (new RestaurantCategoryResource($restaurantCategory))->response();
    }

    public function update(UpdateRestaurantCategoryRequest $request, RestaurantCategory $restaurantCategory): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantCategory->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantCategory)) {
            abort(403);
        }

        $restaurantCategory->update($request->validated());

        return (new RestaurantCategoryResource($restaurantCategory))->response();
    }

    public function destroy(Request $request, RestaurantCategory $restaurantCategory): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantCategory->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantCategory)) {
            abort(403);
        }

        $restaurantCategory->delete();

        return new JsonResponse(null, 204);
    }
}
