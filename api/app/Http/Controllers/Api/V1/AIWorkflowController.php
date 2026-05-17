<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\AI\Workflows\PreparePayrollWorkflow;
use App\AI\Workflows\WeeklyReportWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIWorkflowController extends Controller
{
    public function preparePayroll(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
        ]);

        $workflow = new PreparePayrollWorkflow;
        $result = $workflow->execute(
            (int) $actor->company_id,
            $validated['period_start'],
            $validated['period_end'],
        );

        return response()->json(['data' => $result]);
    }

    public function weeklyReport(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'week_start' => 'nullable|date',
        ]);

        $workflow = new WeeklyReportWorkflow;
        $result = $workflow->execute(
            (int) $actor->company_id,
            $validated['week_start'] ?? null,
        );

        return response()->json(['data' => $result]);
    }
}
