<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\TrainingEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedReportController extends Controller
{
    public function recruitmentPipeline(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $companyId = $user->company_id;

        $pipeline = Applicant::where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json(['data' => $pipeline]);
    }

    public function trainingCompletion(Request $request): JsonResponse
    {
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
                'by_department' => $byDepartment,
                'by_contract_type' => $byContractType,
            ],
        ]);
    }

    public function costAnalysis(Request $request): JsonResponse
    {
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
                'year' => $year,
                'active_loans_total' => $loanCosts,
                'training_enrollments_count' => $trainingCost,
            ],
        ]);
    }
}
