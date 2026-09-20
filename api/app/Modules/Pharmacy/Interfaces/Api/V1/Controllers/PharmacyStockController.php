<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Application\Services\PharmacyStockService;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\StorePharmacyStockAdjustmentRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stock d'officine — PHARMA-003 (#7800).
 *
 * Niveaux par produit (somme des lots NON périmés), lots d'un produit,
 * journal des mouvements (immuable), ajustements d'inventaire (manager).
 */
class PharmacyStockController extends Controller
{
    use ChecksPharmacySolution;

    public function __construct(private readonly PharmacyStockService $stock) {}

    /**
     * Niveaux de stock par produit : disponible = somme des lots non périmés.
     */
    public function levels(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyStockMovement::class);

        $today = Carbon::today()->toDateString();

        $query = PharmacyProduct::query()
            ->where('pharmacy_products.company_id', $actor->company_id)
            ->leftJoin('pharmacy_batches', function ($join) use ($actor): void {
                $join->on('pharmacy_batches.product_id', '=', 'pharmacy_products.id')
                    ->where('pharmacy_batches.company_id', '=', $actor->company_id);
            })
            ->groupBy('pharmacy_products.id')
            ->select('pharmacy_products.*')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN pharmacy_batches.expiry_date >= ? THEN pharmacy_batches.quantity ELSE 0 END), 0) AS available_quantity',
                [$today]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN pharmacy_batches.expiry_date < ? THEN pharmacy_batches.quantity ELSE 0 END), 0) AS expired_quantity',
                [$today]
            );

        if ($request->filled('search')) {
            $needle = mb_strtolower((string) $request->input('search'));
            $like = '%'.addcslashes($needle, '%_\\').'%';
            $query->where(function ($sub) use ($like): void {
                $sub->whereRaw('LOWER(pharmacy_products.name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(pharmacy_products.dci) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(pharmacy_products.barcode) LIKE ?', [$like]);
            });
        }

        $levels = $query->orderBy('pharmacy_products.name')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($levels->items())->map(function (PharmacyProduct $product): array {
                $available = (int) $product->getAttribute('available_quantity');

                return [
                    'product_id' => (int) $product->getAttribute('id'),
                    'name' => $product->name,
                    'dci' => $product->dci,
                    'unit' => $product->unit,
                    'min_stock_level' => $product->min_stock_level,
                    'available_quantity' => $available,
                    'expired_quantity' => (int) $product->getAttribute('expired_quantity'),
                    'below_min_stock' => $product->min_stock_level > 0 && $available < $product->min_stock_level,
                ];
            }),
            'meta' => [
                'current_page' => $levels->currentPage(),
                'last_page' => $levels->lastPage(),
                'per_page' => $levels->perPage(),
                'total' => $levels->total(),
            ],
        ]);
    }

    /**
     * Lots d'un produit (péremptions croissantes — ordre FEFO).
     */
    public function batches(Request $request, PharmacyProduct $product): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($product, $actor->company_id);
        $this->authorize('viewAny', PharmacyStockMovement::class);

        $batches = PharmacyBatch::query()
            ->where('company_id', $actor->company_id)
            ->where('product_id', $product->getAttribute('id'))
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();

        $today = Carbon::today();

        return response()->json([
            'data' => $batches->map(fn (PharmacyBatch $batch): array => [
                'id' => (int) $batch->id,
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'quantity' => $batch->quantity,
                'unit_cost' => (string) $batch->unit_cost,
                'supplier_id' => $batch->supplier_id,
                'received_at' => $batch->received_at?->toIso8601String(),
                'expired' => $batch->expiry_date->isBefore($today),
            ]),
        ]);
    }

    /**
     * Journal des mouvements (immuable), filtres produit / type.
     */
    public function movements(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyStockMovement::class);

        $query = PharmacyStockMovement::query()->where('company_id', $actor->company_id);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $movements = $query->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 25))));

        return response()->json([
            'data' => collect($movements->items())->map(fn (PharmacyStockMovement $movement): array => [
                'id' => (int) $movement->id,
                'product_id' => $movement->product_id,
                'batch_id' => $movement->batch_id,
                'type' => $movement->type,
                'quantity_delta' => $movement->quantity_delta,
                'reason' => $movement->reason,
                'reference_type' => $movement->reference_type,
                'reference_id' => $movement->reference_id,
                'created_by_employee_id' => $movement->created_by_employee_id,
                'created_at' => $movement->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    /**
     * Ajustement d'inventaire (manager, raison obligatoire).
     */
    public function storeAdjustment(StorePharmacyStockAdjustmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', PharmacyStockMovement::class);

        /** @var array{batch_id: int, quantity_delta: int, reason: string, type?: string|null} $payload */
        $payload = $request->validated();

        $batch = DB::transaction(fn (): PharmacyBatch => $this->stock->adjust(
            (string) $actor->company_id,
            $payload['batch_id'],
            $payload['quantity_delta'],
            $payload['reason'],
            (int) $actor->getAttribute('id'),
            $payload['type'] ?? 'adjustment',
        ));

        return response()->json([
            'data' => [
                'batch_id' => (int) $batch->id,
                'product_id' => $batch->product_id,
                'quantity' => $batch->quantity,
            ],
        ], 201);
    }
}
