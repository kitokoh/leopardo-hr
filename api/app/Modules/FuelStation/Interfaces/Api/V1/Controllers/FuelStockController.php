<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Enums\FuelStockMovementType;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelStockReconciliation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\ReconcileFuelStockRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelStockMovementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5803/#5805 — Stocks, mouvements et rapprochement FuelStation
 * (FUEL-009/FUEL-011).
 *
 * Manager principal/rh uniquement (FuelStockPolicy). Rapport d'écart
 * EXPLICABLE : aucun ajustement silencieux — la variance est toujours
 * remontée avec son statut.
 */
class FuelStockController extends Controller
{
    public function __construct(private readonly FuelStockService $stocks)
    {
    }

    public function movements(Request $request, FuelTank $tank): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($tank, $request->user());
        $this->authorize('viewAny', FuelStockReconciliation::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $movements = \App\Modules\FuelStation\Domain\Models\FuelStockMovement::query()
            ->where('company_id', $actor->company_id)
            ->where('tank_id', $tank->id)
            ->orderByDesc('occurred_at')
            ->limit(min(max((int) $request->query('per_page', 50), 1), 100))
            ->get();

        return response()->json(['data' => $movements]);
    }

    public function recordMovement(StoreFuelStockMovementRequest $request, FuelTank $tank): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($tank, $request->user());
        $this->authorize('recordMovement', FuelStockReconciliation::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $type = FuelStockMovementType::tryFrom($request->validated()['type']);

        if ($type === null) {
            return response()->json(['error' => 'fuel_stock_invalid_movement_type'], 422);
        }

        $movement = match ($type) {
            FuelStockMovementType::Delivery => $this->stocks->recordDelivery($tank, (float) $request->validated()['quantity'], $this->context($request, $actor)),
            FuelStockMovementType::Sale => $this->stocks->recordSale($tank, (float) $request->validated()['quantity'], $this->context($request, $actor)),
            FuelStockMovementType::Closing => $this->stocks->recordClosingCount($tank, (float) $request->validated()['quantity'], $this->context($request, $actor)),
            FuelStockMovementType::Adjustment => $this->stocks->recordAdjustment($tank, (float) $request->validated()['quantity'], $this->context($request, $actor)),
            default => throw new \RuntimeException('Type de mouvement non géré.'),
        };

        return response()->json(['data' => $movement], 201);
    }

    public function reconcile(ReconcileFuelStockRequest $request, int $station): JsonResponse
    {
        $this->assertSolutionActive();
        $this->authorize('reconcile', FuelStockReconciliation::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $report = $this->stocks->reconcile(
            $actor->company_id,
            $station,
            $request->validated()['period'],
            $actor->id,
        );

        return response()->json(['data' => $report]);
    }

    public function reports(Request $request): JsonResponse
    {
        $this->assertSolutionActive();
        $this->authorize('viewAny', FuelStockReconciliation::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $query = FuelStockReconciliation::query()->where('company_id', $actor->company_id);

        foreach (['station_id', 'period', 'status'] as $filter) {
            $value = $request->query($filter);
            if ($value !== null && $value !== '') {
                $query->where($filter, $value);
            }
        }

        $reports = $query->orderByDesc('period')->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json(['data' => $reports]);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request, Employee $actor): array
    {
        return [
            'station_id' => $request->validated()['station_id'] ?? null,
            'unit_price' => isset($request->validated()['unit_price']) ? (float) $request->validated()['unit_price'] : null,
            'reference' => $request->validated()['reference'] ?? null,
            'notes' => $request->validated()['notes'] ?? null,
            'created_by' => $actor->id,
            'occurred_at' => $request->validated()['occurred_at'] ?? null,
        ];
    }

    private function guardTenant(FuelTank $tank, mixed $actor): void
    {
        if ($actor instanceof Employee && (string) $tank->company_id !== (string) $actor->company_id) {
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
