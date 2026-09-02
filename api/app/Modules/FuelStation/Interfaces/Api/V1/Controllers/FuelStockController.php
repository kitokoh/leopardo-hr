<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelStockEntry;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelStockEntryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Modules\FuelStation\Domain\Models\FuelStation
use App\Modules\FuelStation\Domain\Models\FuelTank
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\RunFuelReconciliationRequest
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelTankDeliveryRequest
use Illuminate\Database\Eloquent\Model;
use App\Modules\FuelStation\Domain\Models\FuelDelivery
use App\Modules\FuelStation\Domain\Models\FuelStockMovement
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation
use App\Modules\FuelStation\Domain\Models\FuelTank
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\RunFuelReconciliationRequest
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelDeliveryRequest
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelTankDeliveryRequest
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreStockAdjustmentRequest

/**
 * Stocks, cuves et rapprochement (FUEL-009, issue #5803).
 *
 * deny-by-default (FuelStockEntryPolicy) : entrées de stock et
 * rapprochement réservés au manager. Aucun ajustement silencieux
 * (reason obligatoire). Rapprochement idempotent par station/jour.
 */
class FuelStockController extends Controller
{
    public function __construct(private readonly FuelStockService $stocks) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockEntry::class);

        $query = FuelStockEntry::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->filled('product_code')) {
            $query->where('product_code', $request->input('product_code'));
        }

        $entries = $query->orderByDesc('entry_date')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($entries->items())->map(fn (FuelStockEntry $e): array => $this->entryPayload($e)),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    public function store(StoreFuelStockEntryRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelStockEntry::class);

        $entry = $this->stocks->recordEntry($actor, $request->validated());

        return response()->json(['data' => $this->entryPayload($entry)], 201);
    }

    public function level(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockEntry::class);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $productCode = $request->string('product_code', '')->toString();

        if ($productCode === '') {
            abort(422, 'PRODUCT_CODE_REQUIRED');
        }

        $level = $this->stocks->levelFor((string) $actor->company_id, $stationId, $productCode);

        return response()->json([
            'data' => [
                'station_id' => $stationId,
                'product_code' => $productCode,
                'level_litres' => $level,
            ],
        ]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockEntry::class);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $date = $request->filled('date')
            ? Carbon::parse((string) $request->string('date'))
            : now()->subDay();

        $result = $this->stocks->reconcile((string) $actor->company_id, $stationId, $date, $actor->id);

        return response()->json([
            'data' => [
                'run' => $this->runPayload($result['run']),
                'variances' => $result['variances'],
            ],
        ]);
    }

    public function runs(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockEntry::class);

        $runs = FuelReconciliationRun::query()
            ->where('company_id', $actor->company_id)
            ->when($request->filled('station_id'), fn ($q) => $q->where('station_id', $request->input('station_id')))
            ->orderByDesc('run_date')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($runs->items())->map(fn (FuelReconciliationRun $r): array => $this->runPayload($r)),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryPayload(FuelStockEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'company_id' => $entry->company_id,
            'station_id' => $entry->station_id,
            'product_code' => $entry->product_code,
            'quantity' => $entry->quantity,
            'unit_cost' => $entry->unit_cost,
            'entry_type' => $entry->entry_type,
            'reason' => $entry->reason,
            'reference' => $entry->reference,
            'entry_date' => $entry->entry_date->toDateString(),
            'created_by' => $entry->created_by,
            'created_at' => $entry->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(FuelReconciliationRun $run): array
    {
        return [
            'id' => $run->id,
            'company_id' => $run->company_id,
            'station_id' => $run->station_id,
            'run_date' => $run->run_date->toDateString(),
            'status' => $run->status,
            'summary' => $run->summary,
            'last_error' => $run->last_error,
            'started_at' => $run->started_at?->toISOString(),
            'finished_at' => $run->finished_at?->toISOString(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }


    public function storeDelivery(StoreFuelTankDeliveryRequest $request, FuelTank $tank): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($tank, $actor);
        $this->authorize('createDelivery', FuelTankDelivery::class);

        $delivery = $this->stocks->recordDelivery($tank, $actor, $request->validated());

        return response()->json(['data' => $this->deliveryPayload($delivery)], 201);
    }


    public function stocks(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewStocks', FuelTankDelivery::class);

        $query = FuelTank::query()
            ->with('station:id,code,name')
            ->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tanks = $query->orderBy('station_id')->orderBy('code')->get();

        return response()->json([
            'data' => $tanks->map(fn (FuelTank $tank): array => $this->tankPayload($tank)),
        ]);
    }


    public function runReconciliation(RunFuelReconciliationRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($station, $actor);
        $this->authorize('runReconciliation', FuelReconciliationRun::class);

        $runDateRaw = $request->validated()['run_date'] ?? now()->toDateString();
        $runDate = is_string($runDateRaw) ? $runDateRaw : now()->toDateString();
        $result = $this->stocks->reconcile($station, $runDate, $actor);

        return response()->json([
            'data' => $this->runPayload($result['run']),
            'meta' => ['replayed' => $result['replayed']],
        ]);
    }


    public function reconciliations(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAnyReconciliation', FuelReconciliationRun::class);

        $query = FuelReconciliationRun::query()
            ->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $runs = $query
            ->orderByDesc('run_date')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($runs->items())->map(fn (FuelReconciliationRun $run): array => $this->runPayload($run)),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'total' => $runs->total(),
                'per_page' => $runs->perPage(),
            ],
        ]);
    }


    public function showReconciliation(Request $request, FuelReconciliationRun $run): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($run, $actor);
        $this->authorize('viewReconciliation', $run);

        return response()->json(['data' => $this->runPayload($run)]);
    }

    private function deliveryPayload(FuelTankDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'tank_id' => $delivery->tank_id,
            'quantity_minor' => $delivery->quantity_minor,
            'unit_price_minor' => $delivery->unit_price_minor,
            'delivered_at' => $delivery->delivered_at->toIso8601String(),
            'external_id' => $delivery->external_id,
            'notes' => $delivery->notes,
            'created_by' => $delivery->created_by,
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }

    private function tankPayload(FuelTank $tank): array
    {
        return [
            'id' => $tank->id,
            'station_id' => $tank->station_id,
            'station_code' => $tank->relationLoaded('station') ? $tank->station?->code : null,
            'code' => $tank->code,
            'product_type' => $tank->product_type,
            'capacity_minor' => $tank->capacity_minor,
            'current_level_minor' => $tank->current_level_minor,
            'status' => $tank->status,
            'fill_ratio' => $tank->capacity_minor > 0
                ? round((int) $tank->current_level_minor / (int) $tank->capacity_minor, 4)
                : 0.0,
        ];
    }


    private function assertTenantOwned(Model $model, Employee $actor): void
    {
        if ($model->getAttribute('company_id') !== $actor->company_id) {
            abort(404);
        }
    }


    public function movements(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockEntry::class);

        $query = FuelStockEntry::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->filled('product_code')) {
            $query->where('product_code', $request->input('product_code'));
        }

        $entries = $query->orderByDesc('entry_date')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($entries->items())->map(fn (FuelStockEntry $e): array => $this->entryPayload($e)),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ]);
    }


    public function verifyDelivery(Request $request, FuelDelivery $delivery): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockEntry::class);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $productCode = $request->string('product_code', '')->toString();

        if ($productCode === '') {
            abort(422, 'PRODUCT_CODE_REQUIRED');
        }

        $level = $this->stocks->levelFor((string) $actor->company_id, $stationId, $productCode);

        return response()->json([
            'data' => [
                'station_id' => $stationId,
                'product_code' => $productCode,
                'level_litres' => $level,
            ],
        ]);
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

        $tanks = $query->orderBy('station_id')->orderBy('code')->get();

        return response()->json([
            'data' => $tanks->map(fn (FuelTank $tank): array => $this->tankPayload($tank)),
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