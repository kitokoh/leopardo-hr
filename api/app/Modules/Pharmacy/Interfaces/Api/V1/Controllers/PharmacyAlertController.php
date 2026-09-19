<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Interfaces\Api\V1\Traits\ChecksPharmacySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Alertes de stock d'officine — PHARMA-003 (#7800).
 *
 * Vue synthétique pour le fondateur : produits sous leur seuil d'alerte
 * (`min_stock_level`, en stock NON périmé), lots périmant sous N jours
 * (défaut 90) et lots déjà périmés restant à retirer. Tenant-scopé, lecture
 * pour tout employé du tenant.
 */
class PharmacyAlertController extends Controller
{
    use ChecksPharmacySolution;

    private const DEFAULT_EXPIRY_WINDOW_DAYS = 90;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', PharmacyBatch::class);

        $days = max(1, min(365, $request->integer('days', self::DEFAULT_EXPIRY_WINDOW_DAYS)));
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($days);

        // Produits actifs sous leur seuil (le disponible EXCLUT les périmés).
        $available = PharmacyBatch::query()
            ->where('company_id', $actor->company_id)
            ->whereDate('expiry_date', '>=', $today)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->pluck('total', 'product_id');

        $lowStock = PharmacyProduct::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->where('min_stock_level', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn (PharmacyProduct $product): array => [
                'product_id' => (int) $product->getAttribute('id'),
                'name' => $product->name,
                'min_stock_level' => $product->min_stock_level,
                'available_quantity' => (int) ($available[(int) $product->getAttribute('id')] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['available_quantity'] < $row['min_stock_level'])
            ->values();

        $expiringSoon = PharmacyBatch::query()
            ->where('company_id', $actor->company_id)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', $horizon)
            ->orderBy('expiry_date')
            ->get()
            ->map(fn (PharmacyBatch $batch): array => $this->batchAlertPayload($batch, $today));

        $expired = PharmacyBatch::query()
            ->where('company_id', $actor->company_id)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<', $today)
            ->orderBy('expiry_date')
            ->get()
            ->map(fn (PharmacyBatch $batch): array => $this->batchAlertPayload($batch, $today));

        return response()->json([
            'data' => [
                'low_stock' => $lowStock,
                'expiring_soon' => $expiringSoon,
                'expired' => $expired,
            ],
            'meta' => [
                'expiry_window_days' => $days,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function batchAlertPayload(PharmacyBatch $batch, Carbon $today): array
    {
        return [
            'batch_id' => (int) $batch->getAttribute('id'),
            'product_id' => $batch->product_id,
            'batch_number' => $batch->batch_number,
            'expiry_date' => $batch->expiry_date->toDateString(),
            'quantity' => $batch->quantity,
            'days_until_expiry' => (int) $today->diffInDays($batch->expiry_date, false),
        ];
    }
}
