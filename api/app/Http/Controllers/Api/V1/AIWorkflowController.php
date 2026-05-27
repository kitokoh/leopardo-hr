<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\AI\Workflows\PreparePayrollWorkflow;
use App\AI\Workflows\WeeklyReportWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\AI\PreparePayrollAIWorkflowRequest;
use App\Http\Requests\Api\V1\AI\WeeklyReportAIWorkflowRequest;

class AIWorkflowController extends Controller
{
    public function preparePayroll(PreparePayrollAIWorkflowRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $workflow = new PreparePayrollWorkflow;
        $result = $workflow->execute(
            $actor->company_id,
            $validated['period_start'],
            $validated['period_end'],
        );

        return response()->json(['data' => $result]);
    }

    public function weeklyReport(WeeklyReportAIWorkflowRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validated();

        $workflow = new WeeklyReportWorkflow;
        $result = $workflow->execute(
            $actor->company_id,
            $validated['week_start'] ?? null,
        );

        return response()->json(['data' => $result]);
    }
}
