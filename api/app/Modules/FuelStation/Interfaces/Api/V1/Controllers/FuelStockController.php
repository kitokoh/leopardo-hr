<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelDelivery;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStockMovement;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelAdjustmentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelDeliveryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API des stocks FuelStation (FUEL-009, #5803).
 *
 * - `GET /fuel-station/stations/{station}/stock` : niveaux courants par
 *   produit (avec le snapshot de rapprochement du jour demandé, optionnel).
 * - `POST .../deliveries` : réception de livraison (mouvement + stock).
 * - `POST .../adjustments` : ajustement avec raison obligatoire.
 * - `GET .../reconciliations` : snapshots de rapprochement (rejouables).
 *
 * Tenant-scoped (404 sûr cross-tenant avant Policy), manager uniquement.
 */
class FuelStockController extends Controller
{
    public function __construct(private readonly FuelStockService $stock) {}

    public function index(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('viewAny', FuelStockReconciliation::class);

        $productType = $request->filled('product_type')
            ? (string) $request->input('product_type')
            : null;

        $products = FuelStockMovement::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->when($productType !== null, fn ($q) => $q->where('product_type', $productType))
            ->distinct()
            ->pluck('product_type')
            ->values();

        $day = $request->filled('day') ? (string) $request->input('day') : Carbon::today()->toDateString();

        $data = $products->map(function (mixed $product) use ($actor, $station, $day): array {
            $productType = (string) $product;
            $level = $this->stock->currentLevel($actor->company_id, $station->id, $productType);

            /** @var FuelStockReconciliation|null $reconciliation */
            $reconciliation = FuelStockReconciliation::query()
                ->where('company_id', $actor->company_id)
                ->where('station_id', $station->id)
                ->where('product_type', $productType)
                ->where('day', $day)
                ->first();

            return [
                'product_type' => $productType,
                'current_level_minor' => $level,
                'reconciliation' => $reconciliation?->only([
                    'day', 'opening_minor', 'deliveries_minor', 'sales_minor',
                    'adjustments_minor', 'expected_closing_minor', 'metered_delta_minor',
                    'variance_minor', 'status', 'notes', 'computed_at',
                ]),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function deliveries(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('viewAny', FuelStockReconciliation::class);

        $deliveries = FuelDelivery::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->orderByDesc('delivered_at')
            ->paginate((int) min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $deliveries->map(fn (FuelDelivery $delivery): array => $this->deliveryPayload($delivery)),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }

    public function storeDelivery(StoreFuelDeliveryRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelStockReconciliation::class);

        $delivery = $this->stock->receiveDelivery($actor, $station, $request->validated());

        return response()->json(['data' => $this->deliveryPayload($delivery)], 201);
    }

    public function storeAdjustment(StoreFuelAdjustmentRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelStockReconciliation::class);

        /** @var FuelStockMovement $movement */
        $movement = $this->stock->recordAdjustment($actor, $station, $request->validated());

        return response()->json(['data' => $this->movementPayload($movement)], 201);
    }

    public function reconciliations(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('viewAny', FuelStockReconciliation::class);

        $query = FuelStockReconciliation::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id);

        if ($request->filled('day')) {
            $query->where('day', (string) $request->input('day'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $snapshots = $query->orderByDesc('day')->paginate((int) min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $snapshots->map(fn (FuelStockReconciliation $r): array => $this->reconciliationPayload($r)),
            'meta' => [
                'current_page' => $snapshots->currentPage(),
                'last_page' => $snapshots->lastPage(),
                'total' => $snapshots->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryPayload(FuelDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'company_id' => $delivery->company_id,
            'station_id' => $delivery->station_id,
            'tank_id' => $delivery->tank_id,
            'product_type' => $delivery->product_type,
            'quantity_minor' => $delivery->quantity_minor,
            'delivered_at' => $delivery->delivered_at?->toISOString(),
            'source' => $delivery->source,
            'status' => $delivery->status,
            'external_id' => $delivery->external_id,
            'received_at' => $delivery->received_at?->toISOString(),
            'notes' => $delivery->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function movementPayload(FuelStockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'company_id' => $movement->company_id,
            'station_id' => $movement->station_id,
            'tank_id' => $movement->tank_id,
            'product_type' => $movement->product_type,
            'type' => $movement->type,
            'quantity_minor' => $movement->quantity_minor,
            'reason' => $movement->reason,
            'reference' => $movement->reference,
            'idempotency_key' => $movement->idempotency_key,
            'recorded_at' => $movement->recorded_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reconciliationPayload(FuelStockReconciliation $reconciliation): array
    {
        return [
            'id' => $reconciliation->id,
            'company_id' => $reconciliation->company_id,
            'station_id' => $reconciliation->station_id,
            'product_type' => $reconciliation->product_type,
            'day' => $reconciliation->day,
            'opening_minor' => $reconciliation->opening_minor,
            'deliveries_minor' => $reconciliation->deliveries_minor,
            'sales_minor' => $reconciliation->sales_minor,
            'adjustments_minor' => $reconciliation->adjustments_minor,
            'expected_closing_minor' => $reconciliation->expected_closing_minor,
            'metered_delta_minor' => $reconciliation->metered_delta_minor,
            'variance_minor' => $reconciliation->variance_minor,
            'status' => $reconciliation->status,
            'notes' => $reconciliation->notes,
            'computed_at' => $reconciliation->computed_at?->toISOString(),
        ];
    }
}
