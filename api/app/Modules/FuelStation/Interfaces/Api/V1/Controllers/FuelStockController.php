<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationRun;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\RunFuelReconciliationRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelTankDeliveryRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stocks, cuves et rapprochement FuelStation (FUEL-009, issue #5803).
 *
 * - POST /fuel-station/tanks/{tank}/deliveries : livraison idempotente
 *   (external_id unique par tenant).
 * - GET /fuel-station/stocks : niveaux courants par station (manager).
 * - POST /fuel-station/reconciliations : passe de rapprochement rejouable
 *   (un seul run par station/date).
 * - GET /fuel-station/reconciliations[/{run}] : historique et détail.
 *
 * Isolation tenant fail-closed (404 cross-tenant) ; RBAC deny-by-default
 * via FuelStockPolicy ; kill switch 403 si solution inactive.
 */
class FuelStockController extends Controller
{
    public function __construct(private readonly FuelStockService $stocks) {}

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

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
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

    /** @return array<string, mixed> */
    private function runPayload(FuelReconciliationRun $run): array
    {
        return [
            'id' => $run->id,
            'station_id' => $run->station_id,
            'run_date' => $run->run_date->toDateString(),
            'status' => $run->status,
            'summary' => $run->summary,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'last_error' => $run->last_error,
            'created_by' => $run->created_by,
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }

    private function assertTenantOwned(Model $model, Employee $actor): void
    {
        if ($model->getAttribute('company_id') !== $actor->company_id) {
            abort(404);
        }
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
