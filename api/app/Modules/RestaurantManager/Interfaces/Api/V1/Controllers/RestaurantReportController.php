<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * RESTO-701 (#6214) — Rapports agrégés (ventes, occupation, produits, COGS,
 * caisses) + RESTO-703 (#6216) — Dashboard KPIs.
 *
 * Lecture pure, permission `restaurant.reports` — #8180 : prouvée par
 * {@see RestaurantPermissions::canViewReports()} (jamais via Gate : aucune
 * capacité `restaurant.*` n'est définie, `cannot()` retournait 403 à tous).
 * Réponses plates `{data: …}` réalignées sur le contrat initial (RESTO-701,
 * perdu lors de la fusion de dette « PM round 7 »). Périmètre (période,
 * branche) toujours borné au tenant courant.
 */
class RestaurantReportController extends Controller
{
    public function __construct(
        private readonly RestaurantReportService $reports,
        private readonly RestaurantPermissions $permissions = new RestaurantPermissions,
    ) {}

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
        return $this->period($request, fn (string $c, Carbon $f, Carbon $t, ?int $b) => $this->reports->topProducts($c, $f, $t, $b));
    }

    public function cogs(Request $request): JsonResponse
    {
        return $this->period($request, fn (string $c, Carbon $f, Carbon $t, ?int $b) => $this->reports->cogs($c, $f, $t, $b));
    }

    public function pos(Request $request): JsonResponse
    {
        return $this->period($request, fn (string $c, Carbon $f, Carbon $t, ?int $b) => $this->reports->posSessions($c, $f, $t, $b));
    }

    public function kpis(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $this->permissions->canViewReports($actor)) {
            abort(403);
        }

        $request->validate([
            'branch_id' => ['nullable', 'integer'],
        ]);

        $branchId = $request->query('branch_id') !== null ? (int) $request->query('branch_id') : null;

        return response()->json([
            'data' => $this->reports->kpis($actor->company_id, $branchId),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Helper : autorisation `restaurant.reports`, validation from/to (défaut :
     * aujourd'hui) et branche, exécute la closure de rapport, renvoie
     * `{data: …}` à plat (contrat RESTO-701).
     */
    private function period(Request $request, callable $fn): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $this->permissions->canViewReports($actor)) {
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

        // JSON_PRESERVE_ZERO_FRACTION : `rotation` (float) doit rester 1.0
        // dans le payload, pas 1 (contrat RESTO-701, #8180).
        return response()->json([
            'data' => $fn($actor->company_id, $from, $to, $branchId),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
