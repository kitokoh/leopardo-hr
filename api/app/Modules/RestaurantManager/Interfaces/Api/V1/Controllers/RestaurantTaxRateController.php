<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantTaxRateRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantTaxRateRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantTaxRateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-303 (#6184) — CRUD des taux de TVA du référentiel restaurant.
 *
 * Toute résolution d'un `{restaurantTaxRate}` d'un autre tenant renvoie 404
 * (jamais 403, qui révélerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantTaxRatePolicy`.
 */
class RestaurantTaxRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantTaxRate::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $taxRates = RestaurantTaxRate::query()
            ->orderBy('code')
            ->paginate($perPage);

        return RestaurantTaxRateResource::collection($taxRates)->response();
    }

    public function store(StoreRestaurantTaxRateRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantTaxRate::class)) {
            abort(403);
        }

        $taxRate = RestaurantTaxRate::query()->create($request->validated());

        return (new RestaurantTaxRateResource($taxRate))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantTaxRate $restaurantTaxRate): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantTaxRate->company_id) {
            abort(404);
        }

        return (new RestaurantTaxRateResource($restaurantTaxRate))->response();
    }

    public function update(UpdateRestaurantTaxRateRequest $request, RestaurantTaxRate $restaurantTaxRate): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantTaxRate->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantTaxRate)) {
            abort(403);
        }

        $restaurantTaxRate->update($request->validated());

        return (new RestaurantTaxRateResource($restaurantTaxRate))->response();
    }

    public function destroy(Request $request, RestaurantTaxRate $restaurantTaxRate): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantTaxRate->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantTaxRate)) {
            abort(403);
        }

        $restaurantTaxRate->delete();

        return new JsonResponse(null, 204);
    }
}
