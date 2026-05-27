<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\AI\Planning\PlanningOptimizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Requests\Api\V1\Planning\WeeklyOptimizationPlanningRequest;

class PlanningController extends Controller
{
    public function __construct(private readonly PlanningOptimizer $optimizer) {}

    public function weeklyOptimization(WeeklyOptimizationPlanningRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $actor = $request->user();
        $companyId = $actor->company_id;
        $weekStart = $request->input('week_start', Carbon::now()->startOfWeek()->toDateString());

        $result = $this->optimizer->optimizeWeeklyPlanning($companyId, $weekStart);

        return response()->json(['data' => $result]);
    }

    public function shiftRebalancing(Request $request): JsonResponse
    {
        $actor = $request->user();
        $companyId = $actor->company_id;

        $result = $this->optimizer->suggestShiftRebalancing($companyId);

        return response()->json(['data' => $result]);
    }
}
