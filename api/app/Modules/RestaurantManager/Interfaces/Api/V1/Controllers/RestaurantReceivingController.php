<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantReceivingService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantReceivingRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantReceivingResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-503 (#6202) — Réceptions (entrées stock, coût moyen pondéré).
 *
 * La création d'une réception applique les mouvements de stock (raison
 * `receiving`) et recalcule le coût moyen pondéré de chaque ingrédient via
 * `RestaurantStockMovementService` — le tout en une transaction.
 */
class RestaurantReceivingController extends Controller
{
    public function __construct(
        private readonly RestaurantReceivingService $receivings,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantReceiving::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $receivings = RestaurantReceiving::query()
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', (int) $v))
            ->when($request->query('purchase_order_id'), fn ($q, $v) => $q->where('purchase_order_id', (int) $v))
            ->orderByDesc('id')
            ->paginate($perPage);

        return RestaurantReceivingResource::collection($receivings)->response();
    }

    public function store(StoreRestaurantReceivingRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantReceiving::class)) {
            abort(403);
        }

        try {
            $receiving = $this->receivings->receive(
                actor: $actor,
                branchId: (int) $request->validated('branch_id'),
                items: $request->validated('items'),
                purchaseOrderId: $request->validated('purchase_order_id'),
                supplierId: $request->validated('supplier_id'),
                reference: $request->validated('reference'),
                noteRedacted: $request->validated('note_redacted'),
            );
        } catch (QueryException) {
            return response()->json(['message' => 'Réception en double : la référence existe déjà.'], 422);
        }

        return (new RestaurantReceivingResource($receiving))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantReceiving $restaurantReceiving): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantReceiving->company_id) {
            abort(404);
        }

        return (new RestaurantReceivingResource($restaurantReceiving))->response();
    }
}
