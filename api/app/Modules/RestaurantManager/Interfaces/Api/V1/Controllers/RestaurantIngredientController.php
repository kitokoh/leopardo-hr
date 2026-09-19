<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\Concerns\ScopesRestaurantBranchListings;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantIngredientRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantIngredientRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantIngredientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-303 (#6184) — CRUD des ingrédients du référentiel restaurant.
 *
 * Toute résolution d'un `{restaurantIngredient}` d'un autre tenant renvoie
 * 404 (jamais 403, qui révélerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantIngredientPolicy`.
 */
class RestaurantIngredientController extends Controller
{
    use ScopesRestaurantBranchListings;

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantIngredient::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $ingredients = $this->scopeToAccessibleBranches($actor, RestaurantIngredient::query())
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantIngredientResource::collection($ingredients)->response();
    }

    public function store(StoreRestaurantIngredientRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', [RestaurantIngredient::class, $request->validated()['branch_id'] ?? null])) {
            abort(403);
        }

        $ingredient = RestaurantIngredient::query()->create($request->validated());

        return (new RestaurantIngredientResource($ingredient))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantIngredient $restaurantIngredient): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantIngredient->company_id) {
            abort(404);
        }

        // #7599 — lecture ressource-scopée : un employé sans assignation ne
        // lit plus les données métier dès que le scoping est actif.
        if ($actor->cannot('view', $restaurantIngredient)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        return (new RestaurantIngredientResource($restaurantIngredient))->response();
    }

    public function update(UpdateRestaurantIngredientRequest $request, RestaurantIngredient $restaurantIngredient): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantIngredient->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantIngredient)) {
            abort(403);
        }

        $restaurantIngredient->update($request->validated());

        return (new RestaurantIngredientResource($restaurantIngredient))->response();
    }

    public function destroy(Request $request, RestaurantIngredient $restaurantIngredient): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantIngredient->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantIngredient)) {
            abort(403);
        }

        $restaurantIngredient->delete();

        return new JsonResponse(null, 204);
    }
}
