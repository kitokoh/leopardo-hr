<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantBranchRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantBranchRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantBranchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-301 (#6182) — CRUD des succursales (branches) du référentiel restaurant.
 *
 * Toute résolution d'un `{restaurantBranch}` d'un autre tenant renvoie 404
 * (jamais 403, qui révélerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantBranchPolicy`.
 */
class RestaurantBranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantBranch::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $branches = RestaurantBranch::query()
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantBranchResource::collection($branches)->response();
    }

    public function store(StoreRestaurantBranchRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantBranch::class)) {
            abort(403);
        }

        $branch = RestaurantBranch::query()->create($request->validated());

        return (new RestaurantBranchResource($branch))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        return (new RestaurantBranchResource($restaurantBranch))->response();
    }

    public function update(UpdateRestaurantBranchRequest $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantBranch)) {
            abort(403);
        }

        $restaurantBranch->update($request->validated());

        return (new RestaurantBranchResource($restaurantBranch))->response();
    }

    public function destroy(Request $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantBranch)) {
            abort(403);
        }

        $restaurantBranch->delete();

        return new JsonResponse(null, 204);
    }
}
