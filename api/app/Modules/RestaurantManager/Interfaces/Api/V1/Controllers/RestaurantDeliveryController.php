<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantDeliveryService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantDeliveryRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantDeliveryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * RESTO-605 (#6210) — Cycle de livraison (assign/out/deliver/cancel).
 */
class RestaurantDeliveryController extends Controller
{
    public function __construct(
        private readonly RestaurantDeliveryService $deliveries,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantDelivery::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $deliveries = RestaurantDelivery::query()
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', (string) $v))
            ->when($request->query('rider_id'), fn ($q, $v) => $q->where('rider_id', (int) $v))
            ->orderByDesc('id')
            ->paginate($perPage);

        return RestaurantDeliveryResource::collection($deliveries)->response();
    }

    public function store(StoreRestaurantDeliveryRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantDelivery::class)) {
            abort(403);
        }

        try {
            $result = $this->deliveries->create(
                $actor,
                (int) $request->validated('order_id'),
                $request->validated('zone_id'),
                (int) $request->validated('fee_minor'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantDeliveryResource($result['delivery']))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDelivery->company_id) {
            abort(404);
        }

        return (new RestaurantDeliveryResource($restaurantDelivery))->response();
    }

    public function assign(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDelivery->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantDelivery)) {
            abort(403);
        }

        $request->validate(['rider_id' => ['required', 'integer']]);

        /** @var RestaurantDeliveryRider|null $rider */
        $rider = RestaurantDeliveryRider::query()
            ->where('company_id', $actor->company_id)
            ->find($request->input('rider_id'));

        if ($rider === null) {
            return response()->json(['message' => 'Livreur introuvable.'], 404);
        }

        try {
            $delivery = $this->deliveries->assign($restaurantDelivery, $rider);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantDeliveryResource($delivery))->response();
    }

    public function outForDelivery(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        return $this->transition($request, $restaurantDelivery, 'outForDelivery');
    }

    public function deliver(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDelivery->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantDelivery)) {
            abort(403);
        }

        try {
            $delivery = $this->deliveries->deliver($restaurantDelivery, $request->input('delivered_to_contact'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantDeliveryResource($delivery))->response();
    }

    public function cancel(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDelivery->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantDelivery)) {
            abort(403);
        }

        try {
            $delivery = $this->deliveries->cancel($restaurantDelivery, $request->input('reason'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantDeliveryResource($delivery))->response();
    }

    private function transition(Request $request, RestaurantDelivery $delivery, string $method): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $delivery->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $delivery)) {
            abort(403);
        }

        try {
            $updated = $this->deliveries->{$method}($delivery);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantDeliveryResource($updated))->response();
    }
}
