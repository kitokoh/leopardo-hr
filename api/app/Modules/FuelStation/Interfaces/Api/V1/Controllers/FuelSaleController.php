<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Infrastructure\Services\FuelSaleService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelSaleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ventes FuelStation (FUEL-008, issue #5802).
 *
 * Enregistrement idempotent (external_id) par tout employé authentifié ;
 * consultation paginée manager (toutes) / pompiste (ses ventes).
 * Isolation tenant fail-closed (404 cross-tenant).
 */
class FuelSaleController extends Controller
{
    public function __construct(private readonly FuelSaleService $sales) {}

    public function store(StoreFuelSaleRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelSale::class);

        $sale = $this->sales->record($actor, $request->validated());

        return response()->json(['data' => $this->payload($sale)]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelSale::class);

        $query = FuelSale::query()
            ->where('company_id', $actor->company_id)
            ->with('employee:id,first_name,last_name');

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->filled('pump_id')) {
            $query->where('pump_id', $request->input('pump_id'));
        }

        if ($request->filled('cash_session_id')) {
            $query->where('cash_session_id', $request->input('cash_session_id'));
        }

        if ($request->filled('product')) {
            $query->where('product', $request->input('product'));
        }

        $dateFrom = $request->input('date_from');
        if (is_string($dateFrom) && $dateFrom !== '') {
            $query->where('sale_time', '>=', $dateFrom.' 00:00:00');
        }

        $dateTo = $request->input('date_to');
        if (is_string($dateTo) && $dateTo !== '') {
            $query->where('sale_time', '<=', $dateTo.' 23:59:59');
        }

        $sales = $query->orderByDesc('sale_time')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($sales->items())->map(fn (FuelSale $sale): array => $this->payload($sale)),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    public function show(Request $request, FuelSale $sale): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($sale->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $sale);

        return response()->json(['data' => $this->payload($sale)]);
    }

    /**
     * Self-service pompiste : ses propres ventes.
     */
    public function mySales(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $query = FuelSale::query()
            ->where('company_id', $actor->company_id)
            ->where('employee_id', $actor->id);

        $dateFrom = $request->input('date_from');
        if (is_string($dateFrom) && $dateFrom !== '') {
            $query->where('sale_time', '>=', $dateFrom.' 00:00:00');
        }

        $dateTo = $request->input('date_to');
        if (is_string($dateTo) && $dateTo !== '') {
            $query->where('sale_time', '<=', $dateTo.' 23:59:59');
        }

        $sales = $query->orderByDesc('sale_time')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($sales->items())->map(fn (FuelSale $sale): array => $this->payload($sale)),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelSale $sale): array
    {
        return [
            'id' => $sale->id,
            'company_id' => $sale->company_id,
            'station_id' => $sale->station_id,
            'pump_id' => $sale->pump_id,
            'cash_session_id' => $sale->cash_session_id,
            'employee_id' => $sale->employee_id,
            'employee' => $sale->relationLoaded('employee')
                ? [
                    'id' => $sale->employee?->id,
                    'first_name' => $sale->employee?->first_name,
                    'last_name' => $sale->employee?->last_name,
                ]
                : null,
            'product' => $sale->product,
            'quantity' => $sale->quantity,
            'unit_price' => $sale->unit_price,
            'amount' => $sale->amount,
            'sale_time' => $sale->sale_time?->toISOString(),
            'source' => $sale->source,
            'external_id' => $sale->external_id,
            'notes' => $sale->notes,
            'created_at' => $sale->created_at?->toISOString(),
            'updated_at' => $sale->updated_at?->toISOString(),
        ];
    }



    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
