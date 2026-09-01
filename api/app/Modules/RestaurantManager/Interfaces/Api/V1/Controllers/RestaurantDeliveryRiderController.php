<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantDeliveryRiderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantDeliveryRiderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantDeliveryRiderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-605 (#6210) — CRUD des livreurs (référence HR par valeur).
 */
class RestaurantDeliveryRiderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantDeliveryRider::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $riders = RestaurantDeliveryRider::query()
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', (int) $v))
            ->when($request->query('is_active') !== null, fn ($q) => $q->where('is_active', $request->query('is_active') === 'true'))
            ->orderBy('name')
            ->paginate($perPage);

        return RestaurantDeliveryRiderResource::collection($riders)->response();
    }

    public function store(StoreRestaurantDeliveryRiderRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantDeliveryRider::class)) {
            abort(403);
        }

        $rider = RestaurantDeliveryRider::query()->create($request->validated());

        return (new RestaurantDeliveryRiderResource($rider))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantDeliveryRider $restaurantDeliveryRider): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDeliveryRider->company_id) {
            abort(404);
        }

        return (new RestaurantDeliveryRiderResource($restaurantDeliveryRider))->response();
    }

    public function update(UpdateRestaurantDeliveryRiderRequest $request, RestaurantDeliveryRider $restaurantDeliveryRider): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDeliveryRider->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantDeliveryRider)) {
            abort(403);
        }

        $restaurantDeliveryRider->update($request->validated());

        return (new RestaurantDeliveryRiderResource($restaurantDeliveryRider))->response();
    }

    public function destroy(Request $request, RestaurantDeliveryRider $restaurantDeliveryRider): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDeliveryRider->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantDeliveryRider)) {
            abort(403);
        }

        $restaurantDeliveryRider->delete();

        return new JsonResponse(null, 204);
    }
}
