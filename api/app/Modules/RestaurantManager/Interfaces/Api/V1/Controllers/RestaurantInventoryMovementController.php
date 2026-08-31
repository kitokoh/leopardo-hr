<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryMovement;
use App\Modules\RestaurantManager\Infrastructure\Services\StockMovementService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantInventoryMovementRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantInventoryMovementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-501 (#6200) — Mouvements de stock (journal + ajustements manuels).
 *
 * `POST /restaurant/inventory-movements` enregistre un mouvement
 * (adjustment|waste|transfer) : le niveau de stock est mis à jour de façon
 * atomique (verrou ligne, jamais négatif) et le journal est tracé avec sa
 * raison. Les raisons `sale`/`receiving`/`count` sont générées par les flux
 * métier (ventes, réceptions, inventaires) — non acceptées ici.
 */
class RestaurantInventoryMovementController extends Controller
{
    public function __construct(private readonly StockMovementService $movements)
    {
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
            ->with(['ingredient', 'branch'])
            ->when($request->has('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->query('branch_id')))
            ->when($request->has('reason_code'), fn ($query) => $query->where('reason_code', (string) $request->query('reason_code')))
            ->orderByDesc('created_at')
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

        $data = $request->validated();

        $level = $this->movements->apply(
            companyId: $actor->company_id,
            branchId: (int) $data['branch_id'],
            ingredientId: (int) $data['ingredient_id'],
            quantityDelta: (float) $data['quantity_delta'],
            reason: StockMovementReason::from($data['reason_code']),
            note: $data['note_redacted'] ?? null,
            userId: $actor->id,
        );

        /** @var RestaurantInventoryMovement $movement */
        $movement = RestaurantInventoryMovement::query()
            ->where('stock_level_id', $level->id)
            ->orderByDesc('id')
            ->first();

        return (new RestaurantInventoryMovementResource($movement->load(['ingredient', 'branch'])))->response()->setStatusCode(201);
    }
}
