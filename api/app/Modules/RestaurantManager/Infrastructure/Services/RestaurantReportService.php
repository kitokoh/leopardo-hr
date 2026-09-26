<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use Illuminate\Database\Eloquent\Builder;
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
 * Périmètre commandes : statuts `paid` et `closed` (ventes constatées — pas
 * les drafts/cancelled).
 *
 * #8180 — les formes de réponse ont été réalignées sur le contrat initial
 * (tests/Feature/Restaurant/RestaurantReportsTest), perdu lors de la fusion
 * de dette « PM round 7 » (#6214 réécriture) : ventes par jour, occupation
 * (sessions clôturées/couverts/tables actives/rotation), COGS détaillé avec
 * marge, clôtures de caisse avec attendu.
 */
final class RestaurantReportService
{
    private const REVENUE_STATUSES = ['paid', 'closed'];

    /**
     * Ventes par jour : date, nombre de commandes, chiffre, répartition par
     * type de commande.
     *
     * @return array<int, array{date: string, orders: int, revenue_minor: int, by_type: array<string, int>}>
     */
    public function sales(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $orders = $this->revenueOrders($companyId, $from, $to, $branchId)
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

        ksort($byDay);

        return array_values($byDay);
    }

    /**
     * Occupation des tables : sessions clôturées, couverts servis, durée
     * moyenne, tables actives, rotation (sessions clôturées / tables actives).
     *
     * @return array{closed_sessions: int, covers: int, avg_duration_minutes: int, active_tables: int, rotation: float}
     */
    public function occupancy(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $sessions = RestaurantTableSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'closed')
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->where('closed_at', '>=', $from)
            ->where('closed_at', '<=', $to)
            ->get(['covers', 'opened_at', 'closed_at']);

        $tables = RestaurantTable::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
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
     * Top produits (quantité, CA) sur la période — lignes actives de
     * commandes payées/clôturées, tri décroissant par chiffre.
     *
     * @return array<int, array{product_id: int, product_code: string|null, product_name: string|null, quantity: int, revenue_minor: int}>
     */
    public function topProducts(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null, int $limit = 10): array
    {
        $orderIds = $this->revenueOrders($companyId, $from, $to, $branchId)->pluck('id');

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

        return collect($aggregates)
            ->sortByDesc(fn (array $row): int => $row['revenue_minor'])
            ->take($limit)
            ->values()
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
     * vendue × coût moyen des ingrédients) par produit, puis totaux.
     *
     * @return array{products: array<int, array<string, mixed>>, total_cogs_minor: int, total_revenue_minor: int, margin_minor: int}
     */
    public function cogs(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $orderIds = $this->revenueOrders($companyId, $from, $to, $branchId)->pluck('id');

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
     * @return array{closings: int, opening_cash_minor: int, expected_cash_minor: int, counted_cash_minor: int, variance_minor: int}
     */
    public function posSessions(string $companyId, Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $sessions = RestaurantPosSession::query()
            ->where('company_id', $companyId)
            ->where('status', PosSessionStatus::CLOSED->value)
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->where('closed_at', '>=', $from)
            ->where('closed_at', '<=', $to)
            ->get(['opening_cash_minor', 'expected_cash_minor', 'counted_cash_minor', 'variance_minor']);

        return [
            'closings' => $sessions->count(),
            'opening_cash_minor' => (int) $sessions->sum('opening_cash_minor'),
            'expected_cash_minor' => (int) $sessions->sum(fn ($s) => (int) ($s->expected_cash_minor ?? 0)),
            'counted_cash_minor' => (int) $sessions->sum(fn ($s) => (int) ($s->counted_cash_minor ?? 0)),
            'variance_minor' => (int) $sessions->sum(fn ($s) => (int) ($s->variance_minor ?? 0)),
        ];
    }

    /**
     * KPIs du tableau de bord (spec §5.6) : chiffre du jour, commandes,
     * panier moyen, occupation des tables, top produits.
     *
     * @return array<string, mixed>
     */
    public function kpis(string $companyId, ?int $branchId = null): array
    {
        $todayStart = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();

        $orders = $this->revenueOrders($companyId, $todayStart, $todayEnd, $branchId)
            ->get(['id', 'total_minor']);

        $revenue = (int) $orders->sum('total_minor');
        $count = $orders->count();

        return [
            'date' => Carbon::today()->toDateString(),
            'revenue_minor' => $revenue,
            'orders_count' => $count,
            'avg_basket_minor' => $count > 0 ? intdiv($revenue, $count) : 0,
            'occupancy' => $this->occupancy($companyId, $todayStart, $todayEnd, $branchId),
            'top_products' => $this->topProducts($companyId, $todayStart, $todayEnd, $branchId, 5),
        ];
    }

    /**
     * Sérialisation CSV déterministe (RESTO-702) : mêmes filtres → mêmes
     * octets. Colonnes allowlistées par type de rapport.
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

    /**
     * @return array<int, array<int, string>>
     */
    private function csvSales(string $companyId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = [['date', 'orders_count', 'revenue_minor']];

        foreach ($this->sales($companyId, $from, $to, $branchId) as $day) {
            $rows[] = [$day['date'], (string) $day['orders'], (string) $day['revenue_minor']];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvProducts(string $companyId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = [['product_id', 'product_code', 'product_name', 'quantity', 'revenue_minor']];

        foreach ($this->topProducts($companyId, $from, $to, $branchId, 1000) as $line) {
            $rows[] = [
                (string) $line['product_id'],
                (string) ($line['product_code'] ?? ''),
                (string) ($line['product_name'] ?? ''),
                (string) $line['quantity'],
                (string) $line['revenue_minor'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvCogs(string $companyId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $data = $this->cogs($companyId, $from, $to, $branchId);

        return [
            ['from', 'to', 'cogs_minor', 'revenue_minor', 'margin_minor'],
            [
                $from->toDateString(),
                $to->toDateString(),
                (string) $data['total_cogs_minor'],
                (string) $data['total_revenue_minor'],
                (string) $data['margin_minor'],
            ],
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvPos(string $companyId, Carbon $from, Carbon $to, ?int $branchId): array
    {
        $rows = [['pos_session_id', 'closed_at', 'opening_cash_minor', 'counted_cash_minor', 'variance_minor']];

        RestaurantPosSession::query()
            ->where('company_id', $companyId)
            ->where('status', PosSessionStatus::CLOSED->value)
            ->whereBetween('closed_at', [$from, $to])
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
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
        return implode("\n", array_map(
            fn (array $row): string => implode(',', array_map(
                fn (string $value): string => sprintf('"%s"', str_replace('"', '""', $value)),
                $row,
            )),
            $rows,
        ))."\n";
    }

    /**
     * @return Builder<RestaurantOrder>
     */
    private function revenueOrders(string $companyId, Carbon $from, Carbon $to, ?int $branchId): Builder
    {
        return RestaurantOrder::query()
            ->where('company_id', $companyId)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId));
    }

    /**
     * Coût matière théorique : Σ (quantité recette × coût moyen ingrédient) ×
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
}
