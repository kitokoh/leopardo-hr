<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Infrastructure\Services;

use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Domain\Models\PharmacySaleLine;
use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use Illuminate\Support\Carbon;

/**
 * Tableau de bord fondateur — PHARMA-007 (#7804).
 *
 * Toutes les agrégations sont calculées SERVEUR, tenant-scopées, ventes
 * annulées (`voided`) EXCLUES. Montants en décimal string (jamais de
 * float) ; les lots périmés sont exclus de la valorisation disponible mais
 * comptés en « à retirer ».
 */
class PharmacyDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $companyId): array
    {
        $today = Carbon::today();
        $now = Carbon::now();

        return [
            'sales' => $this->salesBlock($companyId, $today, $now),
            'stock' => $this->stockBlock($companyId, $today),
            'top_products' => $this->topProducts($companyId, $now),
            'purchasing' => $this->purchasingBlock($companyId),
            'compliance' => $this->complianceBlock($companyId, $now),
            'as_of' => $now->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesBlock(string $companyId, Carbon $today, Carbon $now): array
    {
        $completed = fn () => PharmacySale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', 'completed');

        $revenueToday = (string) $completed()->whereDate('sold_at', $today)->sum('total_amount');
        $revenue7d = (string) $completed()->where('sold_at', '>=', $now->copy()->subDays(7))->sum('total_amount');
        $revenue30d = (string) $completed()->where('sold_at', '>=', $now->copy()->subDays(30))->sum('total_amount');

        $salesCountToday = $completed()->whereDate('sold_at', $today)->count();
        $salesCount30d = $completed()->where('sold_at', '>=', $now->copy()->subDays(30))->count();

        $averageBasket30d = $salesCount30d > 0
            ? bcdiv($this->decimal($revenue30d), (string) $salesCount30d, 2)
            : '0.00';

        /** @var array<string, string> $paymentBreakdown */
        $paymentBreakdown = [];
        $rows = $completed()
            ->where('sold_at', '>=', $now->copy()->subDays(30))
            ->groupBy('payment_method')
            ->selectRaw('payment_method, SUM(total_amount) AS revenue')
            ->orderBy('payment_method')
            ->get();

        foreach ($rows as $row) {
            $paymentBreakdown[(string) $row->getAttribute('payment_method')] = $this->decimal((string) $row->getAttribute('revenue'));
        }

        return [
            'revenue_today' => $this->decimal($revenueToday),
            'revenue_7d' => $this->decimal($revenue7d),
            'revenue_30d' => $this->decimal($revenue30d),
            'sales_count_today' => $salesCountToday,
            'average_basket_30d' => $averageBasket30d,
            'payment_breakdown_30d' => $paymentBreakdown,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockBlock(string $companyId, Carbon $today): array
    {
        // Valorisation = quantité × coût unitaire des lots NON périmés.
        $valuation = (string) PharmacyBatch::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('expiry_date', '>=', $today)
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) AS valuation')
            ->value('valuation');

        $belowMinQuery = PharmacyProduct::withoutGlobalScopes()
            ->where('pharmacy_products.company_id', $companyId)
            ->where('pharmacy_products.status', 'active')
            ->where('pharmacy_products.min_stock_level', '>', 0)
            ->leftJoin('pharmacy_batches', function ($join) use ($companyId, $today): void {
                $join->on('pharmacy_batches.product_id', '=', 'pharmacy_products.id')
                    ->where('pharmacy_batches.company_id', '=', $companyId)
                    ->where('pharmacy_batches.expiry_date', '>=', $today->toDateString());
            })
            ->groupBy('pharmacy_products.id')
            ->havingRaw('COALESCE(SUM(pharmacy_batches.quantity), 0) < pharmacy_products.min_stock_level')
            ->select('pharmacy_products.id');

        $belowMin = \Illuminate\Support\Facades\DB::query()->fromSub($belowMinQuery->toBase(), 'low_stock')->count();

        $expiringSoon = PharmacyBatch::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<', $today->copy()->addDays(90))
            ->count();

        $expired = PharmacyBatch::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<', $today)
            ->count();

        return [
            'valuation' => $this->decimal($valuation),
            'products_below_min_stock' => $belowMin,
            'batches_expiring_90d' => $expiringSoon,
            'batches_expired' => $expired,
        ];
    }

    /**
     * Top 5 des produits vendus sur 30 jours (quantité et CA), ventes
     * annulées exclues.
     *
     * @return list<array<string, mixed>>
     */
    private function topProducts(string $companyId, Carbon $now): array
    {
        $rows = PharmacySaleLine::withoutGlobalScopes()
            ->where('pharmacy_sale_lines.company_id', $companyId)
            ->join('pharmacy_sales', function ($join) use ($companyId): void {
                $join->on('pharmacy_sales.id', '=', 'pharmacy_sale_lines.sale_id')
                    ->where('pharmacy_sales.company_id', '=', $companyId);
            })
            ->where('pharmacy_sales.status', 'completed')
            ->where('pharmacy_sales.sold_at', '>=', $now->copy()->subDays(30))
            ->join('pharmacy_products', function ($join) use ($companyId): void {
                $join->on('pharmacy_products.id', '=', 'pharmacy_sale_lines.product_id')
                    ->where('pharmacy_products.company_id', '=', $companyId);
            })
            ->groupBy('pharmacy_products.id', 'pharmacy_products.name')
            ->selectRaw('pharmacy_products.id AS product_id, pharmacy_products.name AS product_name')
            ->selectRaw('SUM(pharmacy_sale_lines.quantity) AS quantity_sold')
            ->selectRaw('SUM(pharmacy_sale_lines.line_total) AS revenue')
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->get();

        /** @var list<array<string, mixed>> $top */
        $top = [];

        foreach ($rows as $row) {
            $top[] = [
                'product_id' => (int) $row->getAttribute('product_id'),
                'product_name' => (string) $row->getAttribute('product_name'),
                'quantity_sold' => (int) $row->getAttribute('quantity_sold'),
                'revenue' => $this->decimal((string) $row->getAttribute('revenue')),
            ];
        }

        return $top;
    }

    /**
     * @return array<string, int>
     */
    private function purchasingBlock(string $companyId): array
    {
        $open = PharmacyPurchaseOrder::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', ['draft', 'ordered', 'partially_received'])
            ->count();

        return ['open_purchase_orders' => $open];
    }

    /**
     * @return array<string, int>
     */
    private function complianceBlock(string $companyId, Carbon $now): array
    {
        // Délivrances contrôlées = mouvements `sale` des produits
        // is_controlled (ordonnancier PHARMA-006).
        $controlledDispenses = PharmacyStockMovement::withoutGlobalScopes()
            ->where('pharmacy_stock_movements.company_id', $companyId)
            ->where('pharmacy_stock_movements.type', 'sale')
            ->where('pharmacy_stock_movements.created_at', '>=', $now->copy()->subDays(30))
            ->join('pharmacy_products', function ($join) use ($companyId): void {
                $join->on('pharmacy_products.id', '=', 'pharmacy_stock_movements.product_id')
                    ->where('pharmacy_products.company_id', '=', $companyId);
            })
            ->where('pharmacy_products.is_controlled', true)
            ->count();

        return ['controlled_dispenses_30d' => $controlledDispenses];
    }

    /**
     * Normalise une somme SQL en décimal string à 2 décimales (jamais de
     * float — les agrégats numeric de Postgres arrivent en string).
     */
    private function decimal(string $value): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return '0.00';
        }

        return bcadd($value, '0', 2);
    }
}
