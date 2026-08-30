<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelReconciliationReport;
use App\Modules\FuelStation\Domain\Models\FuelStockDelivery;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankStockLevel;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\ReviewFuelReconciliationRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\RunFuelReconciliationRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelStockDeliveryRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelTankStockLevelRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stocks, cuves et rapprochement FuelStation (FUEL-009, issue #5803).
 *
 * Toutes les routes sont tenant-scoped (Policies deny-by-default,
 * manager uniquement) et gateées par le flag `fuel_station` (403
 * fail-closed). Le rapprochement est REJOUABLE : relancer le même
 * (station, jour) recalcule et remplace le rapport, zéro doublon.
 */
class FuelStockController extends Controller
{
    public function __construct(private readonly FuelStockService $stock) {}

    public function recordLevel(StoreFuelTankStockLevelRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelTankStockLevel::class);

        $level = $this->stock->recordStockLevel($actor, $request->validated());

        return response()->json(['data' => $this->levelPayload($level)]);
    }

    public function tankLevels(Request $request, FuelTank $tank): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($tank->company_id !== $actor->company_id, 404);

        $this->authorize('viewAny', FuelTankStockLevel::class);

        $levels = FuelTankStockLevel::query()
            ->where('tank_id', $tank->id)
            ->orderByDesc('recorded_on')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($levels->items())->map(fn (FuelTankStockLevel $level): array => $this->levelPayload($level)),
            'meta' => ['current_page' => $levels->currentPage(), 'last_page' => $levels->lastPage(), 'total' => $levels->total()],
        ]);
    }

    public function storeDelivery(StoreFuelStockDeliveryRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelStockDelivery::class);

        $delivery = $this->stock->recordDelivery($actor, $request->validated());

        return response()->json(['data' => $this->deliveryPayload($delivery)]);
    }

    public function indexDeliveries(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStockDelivery::class);

        $query = FuelStockDelivery::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $deliveries = $query->orderByDesc('delivered_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($deliveries->items())->map(fn (FuelStockDelivery $delivery): array => $this->deliveryPayload($delivery)),
            'meta' => ['current_page' => $deliveries->currentPage(), 'last_page' => $deliveries->lastPage(), 'total' => $deliveries->total()],
        ]);
    }

    public function receiveDelivery(Request $request, FuelStockDelivery $delivery): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($delivery->company_id !== $actor->company_id, 404);

        $this->authorize('receive', $delivery);

        $delivery = $this->stock->receiveDelivery($actor, $delivery);

        return response()->json(['data' => $this->deliveryPayload($delivery)]);
    }

    public function runReconciliation(RunFuelReconciliationRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelReconciliationReport::class);

        $report = $this->stock->reconcile($actor, $request->validated());

        return response()->json(['data' => $this->reportPayload($report)]);
    }

    public function indexReports(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelReconciliationReport::class);

        $query = FuelReconciliationReport::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reports = $query->orderByDesc('report_date')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($reports->items())->map(fn (FuelReconciliationReport $report): array => $this->reportPayload($report)),
            'meta' => ['current_page' => $reports->currentPage(), 'last_page' => $reports->lastPage(), 'total' => $reports->total()],
        ]);
    }

    public function showReport(Request $request, FuelReconciliationReport $report): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($report->company_id !== $actor->company_id, 404);

        $this->authorize('view', $report);

        return response()->json(['data' => $this->reportPayload($report)]);
    }

    public function reviewReport(ReviewFuelReconciliationRequest $request, FuelReconciliationReport $report): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($report->company_id !== $actor->company_id, 404);

        $this->authorize('review', $report);

        $report = $this->stock->review($actor, $report, $request->validated());

        return response()->json(['data' => $this->reportPayload($report)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function levelPayload(FuelTankStockLevel $level): array
    {
        return [
            'id' => $level->id,
            'tank_id' => $level->tank_id,
            'recorded_on' => $level->recorded_on->toDateString(),
            'level_minor' => $level->level_minor,
            'source_code' => $level->source_code,
            'idempotency_key' => $level->idempotency_key,
            'notes' => $level->notes,
            'recorded_by' => $level->recorded_by,
            'created_at' => $level->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryPayload(FuelStockDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'station_id' => $delivery->station_id,
            'tank_id' => $delivery->tank_id,
            'product_code' => $delivery->product_code,
            'supplier_name' => $delivery->supplier_name,
            'quantity_minor' => $delivery->quantity_minor,
            'unit_code' => $delivery->unit_code,
            'delivered_at' => $delivery->delivered_at->toISOString(),
            'reference' => $delivery->reference,
            'status' => $delivery->status,
            'received_by' => $delivery->received_by,
            'received_at' => $delivery->received_at?->toISOString(),
            'notes' => $delivery->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(FuelReconciliationReport $report): array
    {
        return [
            'id' => $report->id,
            'station_id' => $report->station_id,
            'report_date' => $report->report_date->toDateString(),
            'opening_stock_minor' => $report->opening_stock_minor,
            'deliveries_minor' => $report->deliveries_minor,
            'sales_minor' => $report->sales_minor,
            'expected_stock_minor' => $report->expected_stock_minor,
            'closing_stock_minor' => $report->closing_stock_minor,
            'variance_minor' => $report->variance_minor,
            'status' => $report->status,
            'explanation' => $report->explanation,
            'reviewed_by' => $report->reviewed_by,
            'reviewed_at' => $report->reviewed_at?->toISOString(),
            'created_at' => $report->created_at?->toISOString(),
            'updated_at' => $report->updated_at?->toISOString(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
