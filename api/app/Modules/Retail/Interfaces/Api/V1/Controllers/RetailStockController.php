<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailStockService;
use App\Modules\Retail\Domain\Enums\RetailStockReasonCode;
use App\Modules\Retail\Domain\Models\RetailInventoryMovement;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreRetailStockMovementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stocks du module Retail (BC-17 RETAIL, #7673) : niveaux, mouvements, alertes.
 *
 * deny-by-default (RetailStockLevelPolicy) : lecture membres du tenant,
 * enregistrement d'un mouvement réservé principal/rh. Les quantités ne sont
 * JAMAIS écrites directement : tout passe par RetailStockService (transaction
 * + verrou de ligne, stock jamais négatif). Isolation : Rule::exists scoped
 * company_id sur les mouvements (422 cross-tenant, pas de fuite).
 */
class RetailStockController extends Controller
{
    public function __construct(private readonly RetailStockService $stockService) {}

    /**
     * Niveaux de stock (filtres location_id / product_id / below_threshold).
     */
    public function levels(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailStockLevel::class);

        $query = RetailStockLevel::query()->where('company_id', $actor->company_id);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->boolean('below_threshold')) {
            $query->whereNotNull('alert_threshold')
                ->whereColumn('quantity', '<=', 'alert_threshold');
        }

        $levels = $query
            ->orderBy('location_id')
            ->orderBy('product_id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($levels->items())
                ->map(fn (RetailStockLevel $l): array => $this->levelPayload($l)),
            'meta' => [
                'current_page' => $levels->currentPage(),
                'last_page' => $levels->lastPage(),
                'total' => $levels->total(),
            ],
        ]);
    }

    /**
     * Enregistre un mouvement de stock via RetailStockService (seule voie
     * d'écriture des quantités — transaction + verrou, jamais négatif).
     */
    public function storeMovement(StoreRetailStockMovementRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', RetailStockLevel::class);

        /** @var RetailLocation $location */
        $location = RetailLocation::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($request->integer('location_id'));

        /** @var RetailProduct $product */
        $product = RetailProduct::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($request->integer('product_id'));

        $referenceType = $request->filled('reference_type') ? (string) $request->input('reference_type') : null;
        $note = $request->filled('note') ? (string) $request->input('note') : null;

        $movement = $this->stockService->applyMovement(
            location: $location,
            product: $product,
            quantityDelta: (float) $request->input('quantity_delta'),
            reasonCode: RetailStockReasonCode::from((string) $request->input('reason_code')),
            referenceType: $referenceType,
            referenceId: $request->filled('reference_id') ? $request->integer('reference_id') : null,
            note: $note,
            userId: (int) $actor->id,
        );

        /** @var RetailStockLevel $level */
        $level = RetailStockLevel::query()->findOrFail((int) $movement->stock_level_id);

        return response()->json([
            'data' => [
                'movement' => $this->movementPayload($movement),
                'level' => $this->levelPayload($level),
            ],
        ], 201);
    }

    /**
     * Journal des mouvements (filtres, ordre anté-chronologique).
     */
    public function movements(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailStockLevel::class);

        $query = RetailInventoryMovement::query()->where('company_id', $actor->company_id);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->integer('location_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('reason_code')) {
            $query->where('reason_code', $request->input('reason_code'));
        }

        $movements = $query
            ->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($movements->items())
                ->map(fn (RetailInventoryMovement $m): array => $this->movementPayload($m)),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Alertes de stock bas : niveaux dont la quantité a atteint le seuil.
     */
    public function alerts(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailStockLevel::class);

        $levels = RetailStockLevel::query()
            ->where('company_id', $actor->company_id)
            ->whereNotNull('alert_threshold')
            ->whereColumn('quantity', '<=', 'alert_threshold')
            ->orderBy('location_id')
            ->orderBy('product_id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($levels->items())
                ->map(fn (RetailStockLevel $l): array => $this->levelPayload($l)),
            'meta' => [
                'current_page' => $levels->currentPage(),
                'last_page' => $levels->lastPage(),
                'total' => $levels->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function levelPayload(RetailStockLevel $level): array
    {
        return [
            'id' => $level->id,
            'company_id' => $level->company_id,
            'location_id' => $level->location_id,
            'product_id' => $level->product_id,
            'quantity' => $level->quantity,
            'avg_cost_minor' => $level->avg_cost_minor,
            'reorder_level' => $level->reorder_level,
            'alert_threshold' => $level->alert_threshold,
            'created_at' => $level->created_at?->toIso8601String(),
            'updated_at' => $level->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function movementPayload(RetailInventoryMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'company_id' => $movement->company_id,
            'location_id' => $movement->location_id,
            'product_id' => $movement->product_id,
            'stock_level_id' => $movement->stock_level_id,
            'quantity_delta' => $movement->quantity_delta,
            'reason_code' => $movement->reason_code->value,
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
            'note' => $movement->note,
            'user_id' => $movement->user_id,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }
}
