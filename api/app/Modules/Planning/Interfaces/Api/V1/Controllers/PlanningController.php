<?php

declare(strict_types=1);

namespace App\Modules\Planning\Interfaces\Api\V1\Controllers;

use App\AI\Planning\PlanningOptimizer;
use App\Http\Controllers\Controller;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PlanningController extends Controller
{
    public function __construct(private readonly PlanningOptimizer $optimizer) {}

    /**
     * Vue d'ensemble du planning (`GET /planning`).
     *
     * Le module n'exposait que deux endpoints d'optimisation
     * (`/planning/weekly-optimization`, `/planning/shift-rebalancing`) : le
     * chemin racine `/planning` — celui qu'appelle naturellement un client
     * pour l'écran Planning — renvoyait 404, alors que `/absences`,
     * `/employees`, `/crm/accounts`… exposent tous leur collection racine.
     *
     * Cette route agrège l'existant (aucune nouvelle règle métier) : horaires
     * de travail du tenant, effectif actif, absences de la semaine et
     * couverture par département. Les deux endpoints d'optimisation restent
     * inchangés et sont référencés dans `links` pour la découvrabilité.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'week_start' => 'sometimes|date',
        ]);

        $companyId = (string) currentCompany()->id;
        $weekStart = (string) $request->input('week_start', Carbon::now()->startOfWeek()->toDateString());

        $optimization = $this->optimizer->optimizeWeeklyPlanning($companyId, $weekStart);

        $schedules = Schedule::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'start_time', 'end_time', 'break_minutes', 'work_days', 'is_default']);

        return response()->json([
            'data' => [
                'week' => $optimization['week'],
                'schedules' => $schedules->map(static fn (Schedule $schedule): array => [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'break_minutes' => $schedule->break_minutes,
                    'work_days' => $schedule->work_days,
                    'is_default' => (bool) $schedule->is_default,
                ])->values()->all(),
                'headcount' => [
                    'active_employees' => $optimization['total_employees'],
                    'absences_this_week' => $optimization['total_absences'],
                    'expiring_contracts' => $optimization['expiring_contracts'],
                ],
                'department_coverage' => $optimization['department_coverage'],
                'conflicts' => $optimization['conflicts'],
                'optimization_score' => $optimization['optimization_score'],
                'links' => [
                    'weekly_optimization' => '/api/v1/planning/weekly-optimization',
                    'shift_rebalancing' => '/api/v1/planning/shift-rebalancing',
                ],
            ],
        ]);
    }

    public function weeklyOptimization(Request $request): JsonResponse
    {
        $request->validate([
            'week_start' => 'sometimes|date',
        ]);

        $companyId = (string) currentCompany()->id;
        $weekStart = $request->input('week_start', Carbon::now()->startOfWeek()->toDateString());

        $result = $this->optimizer->optimizeWeeklyPlanning($companyId, $weekStart);

        return response()->json(['data' => $result]);
    }

    public function shiftRebalancing(Request $request): JsonResponse
    {
        $companyId = (string) currentCompany()->id;

        $result = $this->optimizer->suggestShiftRebalancing($companyId);

        return response()->json(['data' => $result]);
    }
}
