<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\AI\Workflows\PreparePayrollWorkflow;
use App\AI\Workflows\WeeklyReportWorkflow;
use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Http\JsonResponse;
use App\Modules\Platform\Interfaces\Api\V1\Requests\PreparePayrollReportRequest;
use App\Modules\Platform\Interfaces\Api\V1\Requests\WeeklyReportRequest;
use Illuminate\Http\Request;

class AIWorkflowController extends Controller
{
    public function preparePayroll(PreparePayrollReportRequest $request): JsonResponse
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

    public function weeklyReport(WeeklyReportRequest $request): JsonResponse
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
