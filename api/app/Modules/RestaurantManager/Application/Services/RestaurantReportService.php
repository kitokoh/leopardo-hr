<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-701/702/703 (#6214/#6215/#6216) — Agrégats de pilotage
 * RestaurantManager (spec §5.6).
 *
 * Tous les agrégats sont calculés SERVEUR à partir des données persistées
 * (jamais de totaux acceptés du client), bornés par `company_id` et filtrés
 * par période/branche. Monnaie : minor units entières.
 *
 * Périodes : `from`/`to` en ISO-8601 (datetime inclusif, `to` borné à
 * 23:59:59 du jour). Périmètre commandes : statuts `paid` et `closed`
 * (ventes constatées — pas les drafts/cancelled).
 */
final class RestaurantReportService
{
    /**
     * Ventes par jour : chiffre, nombre de commandes, panier moyen, par type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sales(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $orders = $this->paidOrders($companyId, $from, $to, $branchId)
            ->get(['total_minor', 'order_type', 'created_at']);

        /** @var array<string, array{date: string, orders: int, revenue_minor: int, by_type: array<string, int>}> $byDay */
        $byDay = [];

        foreach ($orders as $order) {
            $day = $order->created_at?->toDateString() ?? 'unknown';

            $entry = $byDay[$day] ?? ['date' => $day, 'orders' => 0, 'revenue_minor' => 0, 'by_type' => []];

            $entry['orders']++;
            $entry['revenue_minor'] += (int) $order->total_minor;

            $type = $order->order_type->value;
            $entry['by_type'][$type] = ($entry['by_type'][$type] ?? 0) + 1;

            $byDay[$day] = $entry;
        }

        return array_values($byDay);
    }

    /**
     * Occupation des tables : sessions clôturées, couverts, durée moyenne,
     * rotation (sessions clôturées / tables actives).
     *
     * @return array<string, mixed>
     */
    public function occupancy(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $sessions = RestaurantTableSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from !== null, fn ($q) => $q->where('closed_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('closed_at', '<=', $to))
            ->get(['covers', 'opened_at', 'closed_at']);

        $tables = RestaurantTable::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $totalCovers = 0;
        $totalMinutes = 0;

        foreach ($sessions as $session) {
            $totalCovers += (int) $session->covers;
            $minutes = $session->opened_at?->diffInMinutes($session->closed_at) ?? 0;
            $totalMinutes += max(0, $minutes);
        }

        $count = $sessions->count();

        return [
            'closed_sessions' => $count,
            'covers' => $totalCovers,
            'avg_duration_minutes' => $count > 0 ? (int) round($totalMinutes / $count) : 0,
            'active_tables' => $tables,
            'rotation' => $tables > 0 ? round($count / $tables, 2) : 0.0,
        ];
    }

    /**
     * Top produits : quantités et chiffre par produit (lignes actives de
     * commandes payées/clôturées).
     *
     * @return array<int, array<string, mixed>>
     */
    public function products(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId, int $limit = 20): array
    {
        $orderIds = $this->paidOrders($companyId, $from, $to, $branchId)->pluck('id');

        if ($orderIds->isEmpty()) {
            return [];
        }

        $items = RestaurantOrderItem::query()
            ->where('company_id', $companyId)
            ->whereIn('order_id', $orderIds)
            ->where('status', OrderItemStatus::ACTIVE->value)
            ->get(['product_id', 'quantity', 'line_total_minor']);

        /** @var array<int, array{product_id: int, quantity: int, revenue_minor: int}> $aggregates */
        $aggregates = [];

        foreach ($items as $item) {
            $productId = (int) $item->product_id;

            $aggregates[$productId] ??= ['product_id' => $productId, 'quantity' => 0, 'revenue_minor' => 0];
            $aggregates[$productId]['quantity'] += (int) $item->quantity;
            $aggregates[$productId]['revenue_minor'] += (int) $item->line_total_minor;
        }

        $collection = collect($aggregates)
            ->sortByDesc(fn (array $row) => $row['revenue_minor'])
            ->take($limit)
            ->values();

        return $collection
            ->map(function (array $row): array {
                $product = RestaurantProduct::query()->find($row['product_id']);

                return [
                    'product_id' => $row['product_id'],
                    'product_code' => $product?->code,
                    'product_name' => $product?->name,
                    'quantity' => $row['quantity'],
                    'revenue_minor' => $row['revenue_minor'],
                ];
            })
            ->all();
    }

    /**
     * COGS & marge : coût matière théorique consommé (recettes × quantité
     * vendue × coût moyen pondéré des ingrédients) par produit, puis totaux.
     *
     * @return array<string, mixed>
     */
    public function cogs(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $orderIds = $this->paidOrders($companyId, $from, $to, $branchId)->pluck('id');

        if ($orderIds->isEmpty()) {
            return ['products' => [], 'total_cogs_minor' => 0, 'total_revenue_minor' => 0, 'margin_minor' => 0];
        }

        $items = RestaurantOrderItem::query()
            ->where('company_id', $companyId)
            ->whereIn('order_id', $orderIds)
            ->where('status', OrderItemStatus::ACTIVE->value)
            ->get(['product_id', 'quantity', 'line_total_minor']);

        /** @var array<int, int> $quantities */
        $quantities = [];
        /** @var array<int, int> $revenues */
        $revenues = [];

        foreach ($items as $item) {
            $productId = (int) $item->product_id;
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $item->quantity;
            $revenues[$productId] = ($revenues[$productId] ?? 0) + (int) $item->line_total_minor;
        }

        $products = [];
        $totalCogs = 0;
        $totalRevenue = 0;

        foreach ($quantities as $productId => $quantity) {
            $costMinor = $this->recipeCostMinor($companyId, (int) $productId, (int) $quantity);
            $revenue = $revenues[$productId] ?? 0;
            $totalCogs += $costMinor;
            $totalRevenue += $revenue;

            $product = RestaurantProduct::query()->find($productId);

            $products[] = [
                'product_id' => $productId,
                'product_code' => $product?->code,
                'product_name' => $product?->name,
                'quantity' => $quantity,
                'revenue_minor' => $revenue,
                'cogs_minor' => $costMinor,
                'margin_minor' => $revenue - $costMinor,
            ];
        }

        return [
            'products' => $products,
            'total_cogs_minor' => $totalCogs,
            'total_revenue_minor' => $totalRevenue,
            'margin_minor' => $totalRevenue - $totalCogs,
        ];
    }

    /**
     * Clôtures de caisse : nombre, fonds, attendu, compté, écart agrégé.
     *
     * @return array<string, mixed>
     */
    public function pos(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $sessions = RestaurantPosSession::query()
            ->where('company_id', $companyId)
            ->where('status', PosSessionStatus::CLOSED->value)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from !== null, fn ($q) => $q->where('closed_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('closed_at', '<=', $to))
            ->get(['opening_cash_minor', 'expected_cash_minor', 'counted_cash_minor', 'variance_minor']);

        $totals = [
            'closings' => $sessions->count(),
            'opening_cash_minor' => 0,
            'expected_cash_minor' => 0,
            'counted_cash_minor' => 0,
            'variance_minor' => 0,
        ];

        foreach ($sessions as $session) {
            $totals['opening_cash_minor'] += (int) $session->opening_cash_minor;
            $totals['expected_cash_minor'] += (int) ($session->expected_cash_minor ?? 0);
            $totals['counted_cash_minor'] += (int) ($session->counted_cash_minor ?? 0);
            $totals['variance_minor'] += (int) ($session->variance_minor ?? 0);
        }

        return $totals;
    }

    /**
     * KPIs du tableau de bord (spec §5.6) : chiffre du jour, commandes,
     * panier moyen, rotation des tables, top produits.
     *
     * @return array<string, mixed>
     */
    public function kpis(string $companyId, ?int $branchId): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $orders = $this->paidOrders($companyId, $todayStart, $todayEnd, $branchId)
            ->get(['id', 'total_minor']);

        $revenue = (int) $orders->sum('total_minor');
        $count = $orders->count();

        $occupancy = $this->occupancy($companyId, $todayStart, $todayEnd, $branchId);

        return [
            'date' => now()->toDateString(),
            'revenue_minor' => $revenue,
            'orders_count' => $count,
            'avg_basket_minor' => $count > 0 ? (int) round($revenue / $count) : 0,
            'occupancy' => $occupancy,
            'top_products' => $this->products($companyId, $todayStart, $todayEnd, $branchId, 5),
        ];
    }

    /**
     * Lignes CSV d'un rapport (colonnes allowlistées, RESTO-702).
     *
     * @return list<string>
     */
    public function exportCsv(string $reportType, string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        return match ($reportType) {
            'sales' => $this->salesCsv($companyId, $from, $to, $branchId),
            'occupancy' => $this->occupancyCsv($companyId, $from, $to, $branchId),
            'products' => $this->productsCsv($companyId, $from, $to, $branchId),
            'cogs' => $this->cogsCsv($companyId, $from, $to, $branchId),
            'pos' => $this->posCsv($companyId, $from, $to, $branchId),
            default => throw new \InvalidArgumentException('Unsupported report type.'),
        };
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @return \Illuminate\Database\Eloquent\Builder<RestaurantOrder>
     */
    private function paidOrders(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId)
    {
        return RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [OrderStatus::PAID->value, OrderStatus::CLOSED->value])
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->when($from !== null, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()));
    }

    /**
     * Coût matière théorique : Σ (quantité recette × coût moyen pondéré) ×
     * quantité vendue (spec D4 — COGS serveur à partir de la composition).
     */
    private function recipeCostMinor(string $companyId, int $productId, int $quantitySold): int
    {
        $ingredients = DB::table('restaurant_product_ingredients')
            ->join('restaurant_ingredients', 'restaurant_ingredients.id', '=', 'restaurant_product_ingredients.ingredient_id')
            ->where('restaurant_product_ingredients.company_id', $companyId)
            ->where('restaurant_product_ingredients.product_id', $productId)
            ->get([
                'restaurant_product_ingredients.quantity',
                'restaurant_ingredients.avg_cost_minor',
            ]);

        $unitCost = 0;

        foreach ($ingredients as $row) {
            $unitCost += (int) round(((float) $row->quantity) * ((int) ($row->avg_cost_minor ?? 0)));
        }

        return $unitCost * $quantitySold;
    }

    /**
     * @return list<string>
     */
    private function salesCsv(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $rows = $this->sales($companyId, $from, $to, $branchId);

        return $this->csv([
            ['date', 'orders_count', 'revenue_minor'],
            ...array_map(fn (array $row) => [
                $row['date'],
                $row['orders'],
                $row['revenue_minor'],
            ], $rows),
        ]);
    }

    /**
     * @return list<string>
     */
    private function occupancyCsv(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $row = $this->occupancy($companyId, $from, $to, $branchId);

        return $this->csv([
            ['metric', 'value'],
            ['closed_sessions', $row['closed_sessions']],
            ['covers', $row['covers']],
            ['avg_duration_minutes', $row['avg_duration_minutes']],
            ['active_tables', $row['active_tables']],
            ['rotation', $row['rotation']],
        ]);
    }

    /**
     * @return list<string>
     */
    private function productsCsv(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $rows = $this->products($companyId, $from, $to, $branchId, 500);

        return $this->csv([
            ['product_id', 'product_code', 'product_name', 'quantity', 'revenue_minor'],
            ...array_map(fn (array $row) => [
                $row['product_id'],
                $row['product_code'] ?? '',
                $row['product_name'] ?? '',
                $row['quantity'],
                $row['revenue_minor'],
            ], $rows),
        ]);
    }

    /**
     * @return list<string>
     */
    private function cogsCsv(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $data = $this->cogs($companyId, $from, $to, $branchId);

        $lines = [['product_id', 'product_code', 'product_name', 'quantity', 'revenue_minor', 'cogs_minor', 'margin_minor']];

        foreach ($data['products'] as $row) {
            $lines[] = [
                $row['product_id'],
                $row['product_code'] ?? '',
                $row['product_name'] ?? '',
                $row['quantity'],
                $row['revenue_minor'],
                $row['cogs_minor'],
                $row['margin_minor'],
            ];
        }

        $lines[] = ['TOTAL', '', '', '', $data['total_revenue_minor'], $data['total_cogs_minor'], $data['margin_minor']];

        return $this->csv($lines);
    }

    /**
     * @return list<string>
     */
    private function posCsv(string $companyId, ?Carbon $from, ?Carbon $to, ?int $branchId): array
    {
        $row = $this->pos($companyId, $from, $to, $branchId);

        return $this->csv([
            ['metric', 'value'],
            ['closings', $row['closings']],
            ['opening_cash_minor', $row['opening_cash_minor']],
            ['expected_cash_minor', $row['expected_cash_minor']],
            ['counted_cash_minor', $row['counted_cash_minor']],
            ['variance_minor', $row['variance_minor']],
        ]);
    }

    /**
     * Sérialisation CSV simple (RFC 4180 : quote doublé, séparateur virgule).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return list<string>
     */
    private function csv(array $rows): array
    {
        return array_map(
            fn (array $row): string => implode(',', array_map(
                fn ($value): string => sprintf('"%s"', str_replace('"', '""', (string) $value)),
                $row,
            )),
            $rows,
        );
    }
}
