<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * RESTO-701 (#6214) — Rapports agrégés (ventes, occupation, produits, COGS,
 * caisses). Lecture pure, tenant-scopée, agrégats cohérents avec les données
 * sous-jacentes (critère d'acceptation).
 */
final class RestaurantReportService
{
    /** Statuts de commande comptabilisés dans les rapports. */
    private const REVENUE_STATUSES = ['paid', 'closed'];

    /**
     * @return array{revenue_minor: int, orders_count: int, avg_basket_minor: int, tax_minor: int, discount_minor: int}
     */
    public function sales(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $query = RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('created_at', [$from, $to]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $orders = $query->get();

        $revenue = (int) $orders->sum('total_minor');
        $count = $orders->count();
        $tax = (int) $orders->sum('tax_minor');
        $discount = (int) $orders->sum('discount_minor');

        return [
            'revenue_minor' => $revenue,
            'orders_count' => $count,
            'avg_basket_minor' => $count > 0 ? intdiv($revenue, $count) : 0,
            'tax_minor' => $tax,
            'discount_minor' => $discount,
        ];
    }

    /**
     * @return array{sessions_count: int, avg_covers: float, avg_duration_minutes: float, rotation: float}
     */
    public function occupancy(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $query = RestaurantTableSession::query()
            ->where('company_id', $companyId)
            ->whereBetween('opened_at', [$from, $to]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $sessions = $query->get();
        $closed = $sessions->filter(fn ($s) => $s->closed_at !== null);
        $count = $sessions->count();

        $avgCovers = $count > 0 ? (float) $sessions->avg('covers') : 0.0;

        $avgDuration = 0.0;
        if ($closed->isNotEmpty()) {
            $avgDuration = (float) $closed->map(fn ($s) => max(0, $s->opened_at->diffInMinutes($s->closed_at)))->avg();
        }

        $tablesCount = RestaurantTable::query()
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('company_id', $companyId)
            ->count();

        return [
            'sessions_count' => $count,
            'avg_covers' => round($avgCovers, 2),
            'avg_duration_minutes' => round($avgDuration, 2),
            'rotation' => $tablesCount > 0 ? round($count / $tablesCount, 2) : 0.0,
        ];
    }

    /**
     * Top produits (quantité, CA) sur la période.
     *
     * @return array<int, array{product_id: int, quantity: string, revenue_minor: int}>
     */
    public function topProducts(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null, int $limit = 10): array
    {
        $query = RestaurantOrderItem::query()
            ->selectRaw('product_id, SUM(quantity) as qty, SUM(line_total_minor) as revenue')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('order', function (Builder $q) use ($from, $to, $branchId): void {
                $q->whereIn('status', self::REVENUE_STATUSES)
                    ->whereBetween('created_at', [$from, $to]);

                if ($branchId !== null) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $query->map(fn ($row) => [
            'product_id' => (int) $row->product_id,
            'quantity' => (string) $row->qty,
            'revenue_minor' => (int) $row->revenue,
        ])->all();
    }

    /**
     * COGS sur la période (même formule que RESTO-506, agrégée).
     */
    public function cogs(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null): int
    {
        $orders = RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['items' => fn ($q) => $q->where('status', 'active'), 'items.product.ingredients'])
            ->get();

        // Coût moyen par ingrédient (branches du périmètre).
        $avgCosts = RestaurantStockLevel::query()
            ->where('company_id', $companyId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->pluck('avg_cost_minor', 'ingredient_id');

        $total = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;

                if ($product === null) {
                    continue;
                }

                $productCogs = 0;
                foreach ($product->ingredients as $ingredient) {
                    $avgCost = $avgCosts[$ingredient->ingredient_id] ?? 0;
                    $productCogs += (int) round((float) $ingredient->quantity * (int) $avgCost);
                }

                $total += (int) round((float) $item->quantity * $productCogs);
            }
        }

        return $total;
    }

    /**
     * @return array{sessions_count: int, opening_cash_minor: int, counted_cash_minor: int, variance_minor: int}
     */
    public function posSessions(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $query = RestaurantPosSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$from, $to]);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $sessions = $query->get();

        return [
            'sessions_count' => $sessions->count(),
            'opening_cash_minor' => (int) $sessions->sum('opening_cash_minor'),
            'counted_cash_minor' => (int) $sessions->sum('counted_cash_minor'),
            'variance_minor' => (int) $sessions->sum('variance_minor'),
        ];
    }

    /**
     * Sérialisation CSV déterministe (RESTO-702) : mêmes filtres → mêmes
     * octets. Colonnes allowlistées par type de rapport.
     *
     * @return string
     */
    public function toCsv(string $companyId, string $reportType, Carbon $from, Carbon $to, ?int $branchId = null): string
    {
        $rows = match ($reportType) {
            'sales' => $this->csvSales($companyId, $from, $to, $branchId),
            'products' => $this->csvProducts($companyId, $from, $to, $branchId),
            'cogs' => $this->csvCogs($companyId, $from, $to, $branchId),
            'pos' => $this->csvPos($companyId, $from, $to, $branchId),
            default => throw new \InvalidArgumentException('Type de rapport inconnu.'),
        };

        return $this->renderCsv($rows);
    }

    private function csvSales(string $companyId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = [['date', 'orders_count', 'revenue_minor', 'tax_minor', 'discount_minor']];

        $orders = RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $byDay = $orders->groupBy(fn ($o) => $o->created_at->toDateString());

        foreach ($byDay->sortKeys() as $day => $dayOrders) {
            $rows[] = [
                $day,
                (string) $dayOrders->count(),
                (string) (int) $dayOrders->sum('total_minor'),
                (string) (int) $dayOrders->sum('tax_minor'),
                (string) (int) $dayOrders->sum('discount_minor'),
            ];
        }

        return $rows;
    }

    private function csvProducts(string $companyId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = [['product_id', 'quantity', 'revenue_minor']];

        foreach ($this->topProducts($companyId, $from, $to, $branchId, 1000) as $line) {
            $rows[] = [(string) $line['product_id'], $line['quantity'], (string) $line['revenue_minor']];
        }

        return $rows;
    }

    private function csvCogs(string $companyId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        return [
            ['from', 'to', 'cogs_minor'],
            [$from->toDateString(), $to->toDateString(), (string) $this->cogs($companyId, $from, $to, $branchId)],
        ];
    }

    private function csvPos(string $companyId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = [['pos_session_id', 'closed_at', 'opening_cash_minor', 'counted_cash_minor', 'variance_minor']];

        RestaurantPosSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$from, $to])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('closed_at')
            ->get()
            ->each(function ($session) use (&$rows): void {
                $rows[] = [
                    (string) $session->id,
                    $session->closed_at?->toDateTimeString() ?? '',
                    (string) (int) $session->opening_cash_minor,
                    (string) (int) $session->counted_cash_minor,
                    (string) (int) $session->variance_minor,
                ];
            });

        return $rows;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
