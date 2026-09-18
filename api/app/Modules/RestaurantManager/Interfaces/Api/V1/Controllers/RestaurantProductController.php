<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\Concerns\ScopesRestaurantBranchListings;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantProductRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantProductRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-302 (#6183) — CRUD des produits du catalogue restaurant.
 *
 * Toute résolution d'un `{restaurantProduct}` d'un autre tenant renvoie 404
 * (jamais 403, qui révélerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantProductPolicy`.
 */
class RestaurantProductController extends Controller
{
    use ScopesRestaurantBranchListings;

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantProduct::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $products = $this->scopeToAccessibleBranches($actor, RestaurantProduct::query())
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantProductResource::collection($products)->response();
    }

    public function store(StoreRestaurantProductRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', [RestaurantProduct::class, $request->validated()['branch_id'] ?? null])) {
            abort(403);
        }

        $product = RestaurantProduct::query()->create($request->validated());

        return (new RestaurantProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantProduct $restaurantProduct): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantProduct->company_id) {
            abort(404);
        }

        // #7599 — lecture ressource-scopée : un employé sans assignation ne
        // lit plus les données métier dès que le scoping est actif.
        if ($actor->cannot('view', $restaurantProduct)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        return (new RestaurantProductResource($restaurantProduct))->response();
    }

    public function update(UpdateRestaurantProductRequest $request, RestaurantProduct $restaurantProduct): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantProduct->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantProduct)) {
            abort(403);
        }

        $restaurantProduct->update($request->validated());

        return (new RestaurantProductResource($restaurantProduct))->response();
    }

    public function destroy(Request $request, RestaurantProduct $restaurantProduct): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantProduct->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantProduct)) {
            abort(403);
        }

        $restaurantProduct->delete();

        return new JsonResponse(null, 204);
    }
}
