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
}
