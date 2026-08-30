<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * RESTO-701 (#6214) — Rapports agrégés (ventes, occupation, produits, COGS,
 * caisses) + RESTO-703 (#6216) — Dashboard KPIs.
 *
 * Lecture pure, permission `restaurant.reports`, périmètre (période, branche)
 * toujours borné au tenant courant.
 */
class RestaurantReportController extends Controller
{
    public function __construct(
        private readonly RestaurantReportService $reports,
    ) {
    }

    public function sales(Request $request): JsonResponse
    {
        return $this->period($request, fn (string $c, Carbon $f, Carbon $t, ?int $b) => $this->reports->sales($c, $f, $t, $b));
    }

    public function occupancy(Request $request): JsonResponse
    {
        return $this->period($request, fn (string $c, Carbon $f, Carbon $t, ?int $b) => $this->reports->occupancy($c, $f, $t, $b));
    }

    public function products(Request $request): JsonResponse
    {
        return $this->period($request, fn (string $c, Carbon $f, Carbon $t, ?int $b) => ['top_products' => $this->reports->topProducts($c, $f, $t, $b)]);
    }

    public function cogs(Request $request): JsonResponse
    {
        return $this->period($request, fn (string $c, Carbon $f, Carbon $t, ?int $b) => ['cogs_minor' => $this->reports->cogs($c, $f, $t, $b)]);
    }

    public function pos(Request $request): JsonResponse
    {
        return $this->period($request, fn (string $c, Carbon $f, Carbon $t, ?int $b) => $this->reports->posSessions($c, $f, $t, $b));
    }

    public function kpis(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('restaurant.reports')) {
            abort(403);
        }

        $request->validate([
            'branch_id' => ['nullable', 'integer'],
        ]);

        $today = Carbon::today();
        $end = Carbon::today()->endOfDay();
        $branchId = $request->query('branch_id') !== null ? (int) $request->query('branch_id') : null;

        $sales = $this->reports->sales($actor->company_id, $today, $end, $branchId);
        $occupancy = $this->reports->occupancy($actor->company_id, $today, $end, $branchId);
        $topProducts = $this->reports->topProducts($actor->company_id, $today, $end, $branchId, 5);

        return response()->json([
            'data' => [
                'date' => $today->toDateString(),
                'revenue_minor' => $sales['revenue_minor'],
                'orders_count' => $sales['orders_count'],
                'avg_basket_minor' => $sales['avg_basket_minor'],
                'table_rotation' => $occupancy['rotation'],
                'sessions_count' => $occupancy['sessions_count'],
                'top_products' => $topProducts,
            ],
        ]);
    }

    /**
     * Helper : valide from/to (défaut : aujourd'hui) et branche, exécute la
     * closure de rapport, renvoie {period, data}.
     */
    private function period(Request $request, callable $fn): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('restaurant.reports')) {
            abort(403);
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $from = $request->query('from') !== null
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : Carbon::today();
        $to = $request->query('to') !== null
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : Carbon::today()->endOfDay();

        $branchId = $request->query('branch_id') !== null ? (int) $request->query('branch_id') : null;

        return response()->json([
            'data' => [
                'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
                'report' => $fn($actor->company_id, $from, $to, $branchId),
            ],
        ]);
    }
}
