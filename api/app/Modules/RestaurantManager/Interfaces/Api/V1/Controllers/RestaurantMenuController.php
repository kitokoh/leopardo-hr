<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\Concerns\ScopesRestaurantBranchListings;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantMenuRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantMenuRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantMenuResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-304 (#6185) — CRUD des menus (carte/formules) du référentiel restaurant.
 *
 * Toute résolution d'un `{restaurantMenu}` d'un autre tenant renvoie 404
 * (jamais 403, qui révèlerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantMenuPolicy`.
 */
class RestaurantMenuController extends Controller
{
    use ScopesRestaurantBranchListings;

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantMenu::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $menus = $this->scopeToAccessibleBranches($actor, RestaurantMenu::query())
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantMenuResource::collection($menus)->response();
    }

    public function store(StoreRestaurantMenuRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', [RestaurantMenu::class, $request->validated()['branch_id'] ?? null])) {
            abort(403);
        }

        $menu = RestaurantMenu::query()->create($request->validated());

        return (new RestaurantMenuResource($menu))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantMenu $restaurantMenu): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantMenu->company_id) {
            abort(404);
        }

        // #7599 — lecture ressource-scopée : un employé sans assignation ne
        // lit plus les données métier dès que le scoping est actif.
        if ($actor->cannot('view', $restaurantMenu)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        return (new RestaurantMenuResource($restaurantMenu))->response();
    }

    public function update(UpdateRestaurantMenuRequest $request, RestaurantMenu $restaurantMenu): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantMenu->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantMenu)) {
            abort(403);
        }

        $restaurantMenu->update($request->validated());

        return (new RestaurantMenuResource($restaurantMenu))->response();
    }

    public function destroy(Request $request, RestaurantMenu $restaurantMenu): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantMenu->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantMenu)) {
            abort(403);
        }

        $restaurantMenu->delete();

        return new JsonResponse(null, 204);
    }
}
