<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Alertes de stock d'officine — PHARMA-003 (#7800).
 *
 * Trois blocs tenant-scopés : produits sous seuil (`min_stock_level`), lots
 * périmant sous N jours (défaut 90, `?days=`), lots périmés à retirer.
 */
class PharmacyAlertController extends Controller
{
    use ChecksPharmacySolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyStockMovement::class);

        $days = max(1, min(365, $request->integer('days', 90)));
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($days);

        // Produits actifs sous leur seuil d'alerte (stock non périmé).
        /** @var \Illuminate\Database\Eloquent\Collection<int, PharmacyProduct> $lowStock */
        $lowStock = PharmacyProduct::query()
            ->where('pharmacy_products.company_id', $actor->company_id)
            ->where('pharmacy_products.status', 'active')
            ->where('pharmacy_products.min_stock_level', '>', 0)
            ->leftJoin('pharmacy_batches', function ($join) use ($actor, $today): void {
                $join->on('pharmacy_batches.product_id', '=', 'pharmacy_products.id')
                    ->where('pharmacy_batches.company_id', '=', $actor->company_id)
                    ->where('pharmacy_batches.expiry_date', '>=', $today->toDateString());
            })
            ->groupBy('pharmacy_products.id')
            ->havingRaw('COALESCE(SUM(pharmacy_batches.quantity), 0) < pharmacy_products.min_stock_level')
            ->select('pharmacy_products.*')
            ->selectRaw('COALESCE(SUM(pharmacy_batches.quantity), 0) AS available_quantity')
            ->orderBy('pharmacy_products.name')
            ->get();

        $expiringSoon = PharmacyBatch::query()
            ->with('product')
            ->where('company_id', $actor->company_id)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<', $horizon)
            ->orderBy('expiry_date')
            ->get();

        $expired = PharmacyBatch::query()
            ->with('product')
            ->where('company_id', $actor->company_id)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<', $today)
            ->orderBy('expiry_date')
            ->get();

        $batchPayload = fn (PharmacyBatch $batch): array => [
            'batch_id' => (int) $batch->id,
            'product_id' => $batch->product_id,
            'product_name' => $batch->product?->name,
            'batch_number' => $batch->batch_number,
            'expiry_date' => $batch->expiry_date->toDateString(),
            'quantity' => $batch->quantity,
        ];

        return response()->json([
            'data' => [
                'low_stock' => $lowStock->map(fn (PharmacyProduct $product): array => [
                    'product_id' => (int) $product->getAttribute('id'),
                    'name' => $product->name,
                    'min_stock_level' => $product->min_stock_level,
                    'available_quantity' => (int) $product->getAttribute('available_quantity'),
                ]),
                'expiring_soon' => $expiringSoon->map($batchPayload),
                'expired' => $expired->map($batchPayload),
                'meta' => [
                    'expiring_window_days' => $days,
                    'as_of' => $today->toDateString(),
                ],
            ],
        ]);
    }
}
