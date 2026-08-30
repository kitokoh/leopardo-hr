<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelDelivery;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\RunFuelReconciliationRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelDeliveryRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreStockAdjustmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stocks, livraisons et rapprochements FuelStation (FUEL-009, #5803).
 *
 * Toutes les routes sont manager + solution active (fail-closed) +
 * tenant-scoped (404 cross-tenant AVANT tout traitement). Livraisons et
 * ajustements rejouables via `idempotency_key` ; rapprochements rejouables
 * via (station, produit, période, clé) — aucun doublon, aucun ajustement
 * silencieux (écart > tolérance → statut `exception`).
 */
class FuelStockController extends Controller
{
    public function __construct(
        private readonly FuelStockService $service,
    ) {}

    public function movements(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockMovement::class);

        $query = FuelStockMovement::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->input('reason'));
        }

        if ($request->filled('product_type')) {
            $query->where('product_type', $request->input('product_type'));
        }

        $from = $request->input('from');
        $to = $request->input('to');
        if (is_string($from) && $from !== '') {
            $query->where('movement_at', '>=', $from);
        }
        if (is_string($to) && $to !== '') {
            $query->where('movement_at', '<=', $to);
        }

        $movements = $query->orderByDesc('movement_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($movements->items())->map(fn (FuelStockMovement $movement): array => $this->movementPayload($movement)),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    public function storeDelivery(StoreFuelDeliveryRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('createDelivery', FuelDelivery::class);

        $station = $this->stationInTenant($request->integer('station_id'), $actor);
        $result = $this->service->recordDelivery($actor, $station, $request->validated());

        return response()->json([
            'data' => [
                'delivery' => $this->deliveryPayload($result['delivery']),
                'movement' => $this->movementPayload($result['movement']),
                'replayed' => $result['replayed'],
            ],
        ], $result['replayed'] ? 200 : 201);
    }

    public function verifyDelivery(Request $request, FuelDelivery $delivery): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($delivery->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('verifyDelivery', $delivery);

        $delivery = $this->service->verifyDelivery($actor, $delivery);

        return response()->json(['data' => $this->deliveryPayload($delivery)]);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelDelivery::class);

        $query = FuelDelivery::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $deliveries = $query->orderByDesc('delivered_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($deliveries->items())->map(fn (FuelDelivery $delivery): array => $this->deliveryPayload($delivery)),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }

    public function runReconciliation(RunFuelReconciliationRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('reconcile', FuelStockReconciliation::class);

        $station = $this->stationInTenant($request->integer('station_id'), $actor);
        $reconciliation = $this->service->runReconciliation($actor, $station, $request->validated());

        return response()->json(['data' => $this->reconciliationPayload($reconciliation)]);
    }

    public function reconciliations(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockReconciliation::class);

        $query = FuelStockReconciliation::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('product_type')) {
            $query->where('product_type', $request->input('product_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $items = $query->orderByDesc('period_end')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($items->items())->map(fn (FuelStockReconciliation $r): array => $this->reconciliationPayload($r)),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('adjust', FuelStockMovement::class);

        $station = $this->stationInTenant($request->integer('station_id'), $actor);
        $result = $this->service->recordAdjustment($actor, $station, $request->validated());

        return response()->json([
            'data' => [
                'movement' => $this->movementPayload($result['movement']),
                'replayed' => $result['replayed'],
            ],
        ], $result['replayed'] ? 200 : 201);
    }

    private function stationInTenant(int $stationId, Employee $actor): FuelStation
    {
        /** @var FuelStation|null $station */
        $station = FuelStation::query()
            ->where('company_id', $actor->company_id)
            ->find($stationId);

        if (! $station instanceof FuelStation) {
            abort(404);
        }

        return $station;
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /** @return array<string, mixed> */
    private function movementPayload(FuelStockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'station_id' => $movement->station_id,
            'tank_id' => $movement->tank_id,
            'product_type' => $movement->product_type,
            'quantity_minor' => $movement->quantity_minor,
            'direction' => $movement->direction,
            'reason' => $movement->reason,
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
            'movement_at' => $movement->movement_at?->toISOString(),
            'notes' => $movement->notes,
            'created_at' => $movement->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function deliveryPayload(FuelDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'station_id' => $delivery->station_id,
            'tank_id' => $delivery->tank_id,
            'product_type' => $delivery->product_type,
            'quantity_minor' => $delivery->quantity_minor,
            'supplier' => $delivery->supplier,
            'reference_number' => $delivery->reference_number,
            'status' => $delivery->status,
            'delivered_at' => $delivery->delivered_at?->toISOString(),
            'verified_at' => $delivery->verified_at?->toISOString(),
            'notes' => $delivery->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function reconciliationPayload(FuelStockReconciliation $reconciliation): array
    {
        return [
            'id' => $reconciliation->id,
            'station_id' => $reconciliation->station_id,
            'product_type' => $reconciliation->product_type,
            'period_start' => $reconciliation->period_start?->toDateString(),
            'period_end' => $reconciliation->period_end?->toDateString(),
            'status' => $reconciliation->status,
            'opening_minor' => $reconciliation->opening_minor,
            'delivered_minor' => $reconciliation->delivered_minor,
            'sold_minor' => $reconciliation->sold_minor,
            'metered_delta_minor' => $reconciliation->metered_delta_minor,
            'measured_close_minor' => $reconciliation->measured_close_minor,
            'theoretical_close_minor' => $reconciliation->theoretical_close_minor,
            'variance_minor' => $reconciliation->variance_minor,
            'variance_tolerance_minor' => $reconciliation->variance_tolerance_minor,
            'explanation' => $reconciliation->explanation,
            'completed_at' => $reconciliation->completed_at?->toISOString(),
        ];
    }
}
