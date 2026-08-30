<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\CreateOrderAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-402 (#6189) — Commandes restaurant : création idempotente, liste et
 * détail. Les totaux ne sont jamais acceptés du client (calcul serveur,
 * RESTO-403/405) ; le rejeu d'une même `idempotency_key` retourne la commande
 * existante (200) au lieu d'un doublon (201).
 */
class RestaurantOrderController extends Controller
{
    public function __construct(private readonly CreateOrderAction $createOrderAction)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantOrder::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $orders = RestaurantOrder::query()
            ->with(['items', 'payments'])
            ->where('company_id', $actor->company_id)
            ->when($request->has('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->query('branch_id')))
            ->when($request->has('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return RestaurantOrderResource::collection($orders)->response();
    }

    public function store(StoreRestaurantOrderRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantOrder::class)) {
            abort(403);
        }

        $result = $this->createOrderAction->create($actor, $request->validated());

        $response = (new RestaurantOrderResource($result['order']))->response();

        return $result['created'] ? $response->setStatusCode(201) : $response;
    }

    public function show(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        $restaurantOrder->load(['items', 'payments']);

        return (new RestaurantOrderResource($restaurantOrder))->response();
    }
}
