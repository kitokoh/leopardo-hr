<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Planning\Interfaces\Api\V1\Requests\DailySummaryRequest;
use App\Modules\Planning\Interfaces\Api\V1\Requests\QuickEstimateRequest;
use App\Modules\Planning\Interfaces\Api\V1\Requests\ReceiptRequest;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Infrastructure\Services\EstimationService;
use App\Support\I18nCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * EstimationController — manager-scoped salary estimation per employee.
 *
 * Migrated from App\Http\Controllers\Api\V1\EstimationController.
 * Self-service equivalent lives in MeController (HR module).
 * All endpoints require the Employee policy — `view` (scope équipe) pour
 * quickEstimate/receipt/dailySummary (#3943).
 */
class EstimationController extends Controller
{
    public function __construct(
        private readonly EstimationService $estimationService,
    ) {}

    public function dailySummary(DailySummaryRequest $request, string $employeeId): JsonResponse
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $this->authorize('view', $employee);

        $summary = $this->estimationService->dailySummary(
            employee: $employee,
            date: $request->validated('date'),
        );

        return new JsonResponse(['data' => $summary]);
    }

    public function quickEstimate(QuickEstimateRequest $request, string $employeeId): JsonResponse
    {
        // #3943 : `view` (et non `viewAny`) — le scope équipe (managesTeamMemberOf)
        // s'applique aux managers dept/superviseur, et l'auto-accès employé aussi.
        $employee = Employee::query()->findOrFail($employeeId);
        $this->authorize('view', $employee);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $request->validated('from'),
            to: $request->validated('to'),
        );

        return new JsonResponse(['data' => $estimate]);
    }

    public function receipt(ReceiptRequest $request, string $employeeId): Response
    {
        // #3943 : `view` (et non `viewAny`) — même scope équipe que
        // quickEstimate/dailySummary : un superviseur ne télécharge un reçu
        // d'estimation que pour ses propres employés.
        $employee = Employee::query()->findOrFail($employeeId);
        $this->authorize('view', $employee);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $request->validated('from'),
            to: $request->validated('to'),
        );

        $company = currentCompany();

        // The receipt is about $employee's data, not the authenticated actor
        // (manager) who requested it — render it in the employee's language.
        App::setLocale(I18nCatalog::normalizeLocale(
            $employee->preferred_language ?? $company->language
        ));

        $pdf = Pdf::loadView('pdf.receipt', [
            'company'  => $company,
            'employee' => $employee,
            'estimate' => $estimate,
        ]);

        $fileName = sprintf(
            'receipt_estimate_employee_%s_%s_%s.pdf',
            $employee->id,
            $request->validated('from'),
            $request->validated('to'),
        );

        return $pdf->download($fileName);
    }
}
