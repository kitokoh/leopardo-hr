<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Exceptions\RestaurantStockException;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantStockMovementService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantInventoryMovementRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantInventoryMovementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-501 (#6200) — Journal des mouvements de stock.
 *
 * La création d'un mouvement passe par `RestaurantStockMovementService`
 * (verrou transactionnel, jamais de stock négatif, traçabilité complète).
 * Lecture filtrée par (branche, ingrédient, raison, référence).
 */
class RestaurantInventoryMovementController extends Controller
{
    public function __construct(
        private readonly RestaurantStockMovementService $stockMovements,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantInventoryMovement::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $movements = RestaurantInventoryMovement::query()
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', (int) $v))
            ->when($request->query('ingredient_id'), fn ($q, $v) => $q->where('ingredient_id', (int) $v))
            ->when($request->query('reason_code'), fn ($q, $v) => $q->where('reason_code', (string) $v))
            ->when($request->query('reference_type'), fn ($q, $v) => $q->where('reference_type', (string) $v))
            ->when($request->query('reference_id'), fn ($q, $v) => $q->where('reference_id', (int) $v))
            ->orderByDesc('id')
            ->paginate($perPage);

        return RestaurantInventoryMovementResource::collection($movements)->response();
    }

    public function store(StoreRestaurantInventoryMovementRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantInventoryMovement::class)) {
            abort(403);
        }

        try {
            $movement = $this->stockMovements->apply(
                companyId: $actor->company_id,
                branchId: (int) $request->validated('branch_id'),
                ingredientId: (int) $request->validated('ingredient_id'),
                quantityDelta: (string) $request->validated('quantity_delta'),
                reason: StockMovementReason::from((string) $request->validated('reason_code')),
                referenceType: $request->validated('reference_type'),
                referenceId: $request->validated('reference_id') !== null ? (int) $request->validated('reference_id') : null,
                noteRedacted: $request->validated('note_redacted'),
                userId: (int) $actor->id,
            );
        } catch (RestaurantStockException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantInventoryMovementResource($movement))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantInventoryMovement $restaurantInventoryMovement): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantInventoryMovement->company_id) {
            abort(404);
        }

        return (new RestaurantInventoryMovementResource($restaurantInventoryMovement))->response();
    }
}
