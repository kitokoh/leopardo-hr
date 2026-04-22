<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Estimation\DailySummaryRequest;
use App\Http\Requests\Api\V1\Estimation\QuickEstimateRequest;
use App\Http\Requests\Api\V1\Estimation\ReceiptRequest;
use App\Models\Employee;
use App\Services\EstimationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class EstimationController extends Controller
{
    public function __construct(private readonly EstimationService $estimationService) {}

    public function dailySummary(DailySummaryRequest $request, string $employeeId): JsonResponse
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $this->authorize('view', $employee);

        $summary = $this->estimationService->dailySummary(
            employee: $employee,
            date: $request->validated('date')
        );

        return new JsonResponse(['data' => $summary]);
    }

    public function quickEstimate(QuickEstimateRequest $request, string $employeeId): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $employee = Employee::query()->findOrFail($employeeId);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $request->validated('from'),
            to: $request->validated('to')
        );

        return new JsonResponse(['data' => $estimate]);
    }

    public function receipt(ReceiptRequest $request, string $employeeId): Response
    {
        $this->authorize('viewAny', Employee::class);

        $employee = Employee::query()->findOrFail($employeeId);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $request->validated('from'),
            to: $request->validated('to')
        );

        $company = app('current_company');

        $pdf = Pdf::loadView('pdf.receipt', [
            'company' => $company,
            'employee' => $employee,
            'estimate' => $estimate,
        ]);

        $fileName = sprintf(
            'receipt_estimate_employee_%s_%s_%s.pdf',
            $employee->id,
            $request->validated('from'),
            $request->validated('to')
        );

        return $pdf->download($fileName);
    }
}
