<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantSupplierRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantSupplierRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantSupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-305 (#6186) — CRUD des fournisseurs du référentiel restaurant.
 *
 * Toute résolution d'un `{restaurantSupplier}` d'un autre tenant renvoie 404
 * (jamais 403, qui révèlerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantSupplierPolicy`.
 */
class RestaurantSupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantSupplier::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $suppliers = RestaurantSupplier::query()
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantSupplierResource::collection($suppliers)->response();
    }

    public function store(StoreRestaurantSupplierRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantSupplier::class)) {
            abort(403);
        }

        $supplier = RestaurantSupplier::query()->create($request->validated());

        return (new RestaurantSupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantSupplier $restaurantSupplier): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantSupplier->company_id) {
            abort(404);
        }

        return (new RestaurantSupplierResource($restaurantSupplier))->response();
    }

    public function update(UpdateRestaurantSupplierRequest $request, RestaurantSupplier $restaurantSupplier): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantSupplier->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantSupplier)) {
            abort(403);
        }

        $restaurantSupplier->update($request->validated());

        return (new RestaurantSupplierResource($restaurantSupplier))->response();
    }

    public function destroy(Request $request, RestaurantSupplier $restaurantSupplier): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantSupplier->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantSupplier)) {
            abort(403);
        }

        $restaurantSupplier->delete();

        return new JsonResponse(null, 204);
    }
}
