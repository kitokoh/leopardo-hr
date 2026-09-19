<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Application\Services\PharmacyStockService;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use App\Modules\Pharmacy\Interfaces\Api\V1\Requests\StorePharmacyAdjustmentRequest;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Stock d'officine par lots — PHARMA-003 (#7800).
 *
 * Lecture pour tout employé du tenant (niveaux, lots, mouvements paginés) ;
 * ajustements d'inventaire réservés aux managers (PharmacyStockPolicy).
 * Le stock DISPONIBLE exclut toujours les lots périmés. Aucune écriture
 * directe des quantités : tout passe par PharmacyStockService (mouvements
 * immuables, verrou pessimiste).
 */
class PharmacyStockController extends Controller
{
    use ChecksPharmacySolution;

    public function __construct(private readonly PharmacyStockService $stockService) {}

    /**
     * Niveaux de stock par produit : somme des lots NON périmés, seuil
     * d'alerte et quantité périmée restant à retirer.
     */
    public function levels(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyBatch::class);

        $products = PharmacyProduct::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        /** @var list<int> $productIds */
        $productIds = collect($products->items())->map(fn (PharmacyProduct $product): int => (int) $product->getAttribute('id'))->all();

        $available = PharmacyBatch::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('product_id', $productIds)
            ->whereDate('expiry_date', '>=', Carbon::today())
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id');

        $expired = PharmacyBatch::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('product_id', $productIds)
            ->whereDate('expiry_date', '<', Carbon::today())
            ->where('quantity', '>', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id');

        return response()->json([
            'data' => collect($products->items())->map(function (PharmacyProduct $product) use ($available, $expired): array {
                $id = (int) $product->getAttribute('id');

                return [
                    'product_id' => $id,
                    'name' => $product->name,
                    'min_stock_level' => $product->min_stock_level,
                    'available_quantity' => (int) ($available[$id] ?? 0),
                    'expired_quantity' => (int) ($expired[$id] ?? 0),
                    'below_min_stock' => $product->min_stock_level > 0 && (int) ($available[$id] ?? 0) < $product->min_stock_level,
                ];
            }),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Lots du tenant (filtre produit, tri par péremption croissante —
     * l'ordre de délivrance FEFO).
     */
    public function batches(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyBatch::class);

        $query = PharmacyBatch::query()->where('company_id', $actor->company_id);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->boolean('in_stock_only')) {
            $query->where('quantity', '>', 0);
        }

        $batches = $query->orderBy('expiry_date')->orderBy('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($batches->items())->map(fn (PharmacyBatch $batch): array => $this->batchPayload($batch)),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        ]);
    }

    /**
     * Journal des mouvements (immuable, paginé, du plus récent au plus
     * ancien ; filtres produit, lot, type).
     */
    public function movements(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyBatch::class);

        $query = PharmacyStockMovement::query()->where('company_id', $actor->company_id);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->integer('batch_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $movements = $query->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($movements->items())->map(fn (PharmacyStockMovement $movement): array => [
                'id' => (int) $movement->getAttribute('id'),
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
     * Ajustement d'inventaire (manager, raison obligatoire) : délégué à
     * PharmacyStockService — jamais de lot négatif, mouvement journalisé.
     */
    public function storeAdjustment(StorePharmacyAdjustmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('adjust', PharmacyBatch::class);

        /** @var array{batch_id: int, quantity_delta: int, reason: string, type?: string|null} $payload */
        $payload = $request->validated();

        /** @var PharmacyBatch|null $batch */
        $batch = PharmacyBatch::query()
            ->where('company_id', $actor->company_id)
            ->find($payload['batch_id']);

        if (! $batch instanceof PharmacyBatch) {
            abort(404);
        }

        $adjusted = $this->stockService->adjust(
            batch: $batch,
            quantityDelta: $payload['quantity_delta'],
            reason: $payload['reason'],
            employeeId: (int) $actor->getAttribute('id'),
            type: $payload['type'] ?? PharmacyStockMovement::TYPE_ADJUSTMENT,
        );

        return response()->json(['data' => $this->batchPayload($adjusted)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(PharmacyBatch $batch): array
    {
        return [
            'id' => (int) $batch->getAttribute('id'),
            'product_id' => $batch->product_id,
            'supplier_id' => $batch->supplier_id,
            'batch_number' => $batch->batch_number,
            'expiry_date' => $batch->expiry_date->toDateString(),
            'quantity' => $batch->quantity,
            'unit_cost' => $batch->unit_cost,
            'received_at' => $batch->received_at?->toIso8601String(),
            'is_expired' => $batch->isExpired(),
        ];
    }
}
