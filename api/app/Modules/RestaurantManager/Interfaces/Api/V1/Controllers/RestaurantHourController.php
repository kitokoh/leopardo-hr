<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantHour;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantHourRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantHourRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantHourResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-304 (#6185) — CRUD des horaires d'ouverture par branche.
 *
 * Toute résolution d'un `{restaurantHour}` d'un autre tenant renvoie 404
 * (jamais 403, qui révèlerait l'existence de la ressource) : le contrôle
 * `company_id` précède systématiquement l'appel à `RestaurantHourPolicy`.
 */
class RestaurantHourController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantHour::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $hours = RestaurantHour::query()
            ->orderBy('day_of_week')
            ->orderBy('id')
            ->paginate($perPage);

        return RestaurantHourResource::collection($hours)->response();
    }

    public function store(StoreRestaurantHourRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantHour::class)) {
            abort(403);
        }

        $hour = RestaurantHour::query()->create($request->validated());

        return (new RestaurantHourResource($hour))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantHour $restaurantHour): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantHour->company_id) {
            abort(404);
        }

        return (new RestaurantHourResource($restaurantHour))->response();
    }

    public function update(UpdateRestaurantHourRequest $request, RestaurantHour $restaurantHour): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantHour->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantHour)) {
            abort(403);
        }

        $restaurantHour->update($request->validated());

        return (new RestaurantHourResource($restaurantHour))->response();
    }

    public function destroy(Request $request, RestaurantHour $restaurantHour): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantHour->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantHour)) {
            abort(403);
        }

        $restaurantHour->delete();

        return new JsonResponse(null, 204);
    }
}
