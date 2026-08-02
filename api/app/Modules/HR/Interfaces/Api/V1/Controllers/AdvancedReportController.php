<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Domain\Contracts\ApplicantPipelineReaderInterface;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\EmployeeLoan;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AdvancedReportController — cross-domain HR analytics for managers.
 *
 * Migrated from App\Http\Controllers\Api\V1\AdvancedReportController.
 * All endpoints restricted to managers (isManager check).
 */
class AdvancedReportController extends Controller
{
    public function __construct(
        private readonly ApplicantPipelineReaderInterface $applicantPipelineReader,
    ) {}

    public function recruitmentPipeline(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $pipeline = $this->applicantPipelineReader->countByStatus((string) $user->company_id);

        return response()->json(['data' => $pipeline]);
    }

    public function trainingCompletion(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $companyId = $user->company_id;

        $stats = TrainingEnrollment::where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json(['data' => $stats]);
    }

    public function loanSummary(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $companyId = $user->company_id;

        $stats = EmployeeLoan::where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('status')
            ->get();

        return response()->json(['data' => $stats]);
    }

    public function demographicBreakdown(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $companyId = $user->company_id;

        $byDepartment = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->selectRaw('department_id, COUNT(*) as count')
            ->groupBy('department_id')
            ->get();

        $byContractType = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->selectRaw('contract_type, COUNT(*) as count')
            ->groupBy('contract_type')
            ->get();

        return response()->json([
            'data' => [
                'by_department'    => $byDepartment,
                'by_contract_type' => $byContractType,
            ],
        ]);
    }

    public function costAnalysis(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $companyId = $user->company_id;
        $year = $request->integer('year', (int) now()->format('Y'));

        $loanCosts = EmployeeLoan::where('company_id', $companyId)
            ->whereIn('status', ['disbursed', 'repaying'])
            ->sum('amount');

        $trainingCost = TrainingEnrollment::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->count();

        return response()->json([
            'data' => [
                'year'                        => $year,
                'active_loans_total'          => $loanCosts,
                'training_enrollments_count'  => $trainingCost,
            ],
        ]);
    }
}

