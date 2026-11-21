<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HR\Infrastructure\Services\HrDashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tableau de bord RH.
 *
 * Ce contrôleur ne porte plus que le contrat HTTP : lecture de l'acteur
 * authentifié, parsing/validation des entrées, forme de la réponse. Les
 * agrégations vivent dans `HrDashboardStatsService` (couche Infrastructure),
 * où elles sont testables sans monter une requête HTTP.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly HrDashboardStatsService $stats,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->stats->summary((string) $user->company_id),
        ]);
    }

    public function managerDigest(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->stats->managerDigest($user, now()->toDateString()),
        ]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        // #4499 : limit borné (1..100) — une valeur négative = « illimité » en
        // PostgreSQL, une valeur énorme vidait tout audit_logs en une requête.
        $limit = max(1, min(100, $request->integer('limit', 20)));

        return response()->json([
            'data' => $this->stats->recentActivity((string) $user->company_id, $limit),
        ]);
    }

    public function kpi(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $monthInput = $request->input('month');
        $month = is_string($monthInput) ? $monthInput : now()->format('Y-m');
        $periodStart = now();

        try {
            // Carbon normalise silencieusement `2026-13` vers `2027-01` ;
            // valider explicitement le mois avant le parsing pour respecter
            // le contrat de fallback sur le mois courant.
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) === 1) {
                $parsedMonth = Carbon::createFromFormat('!Y-m', $month);
                if ($parsedMonth instanceof Carbon) {
                    $periodStart = $parsedMonth;
                }
            }
        } catch (\Throwable) {
            // Un mois contrôlé par le client ne doit jamais produire un 500 :
            // on garde la fenêtre KPI sur le mois courant.
        }

        $periodStart = $periodStart->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        return response()->json([
            'data' => [
                'month' => $periodStart->format('Y-m'),
                ...$this->stats->kpi((string) $user->company_id, $periodStart, $periodEnd),
            ],
        ]);
    }

    /**
     * Admin company summary — principals only (full view).
     */
    public function adminSummary(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $companyId = (string) $employee->company_id;

        $data = $this->stats->summary($companyId);
        $data['role_counts'] = $this->stats->roleCounts($companyId);

        return response()->json(['data' => $data]);
    }

    /**
     * RH summary — rh managers only.
     */
    public function rhSummary(Request $request): JsonResponse
    {
        return $this->summary($request); // For now same as summary — can be filtered
    }

    /**
     * Comptable summary — comptable managers only.
     */
    public function comptableSummary(Request $request): JsonResponse
    {
        return $this->summary($request);
    }

    /**
     * Marketing summary — marketing managers only.
     */
    public function marketingSummary(Request $request): JsonResponse
    {
        return $this->summary($request);
    }
}
