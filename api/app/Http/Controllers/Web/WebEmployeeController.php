<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\EstimationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebEmployeeController extends Controller
{
    public function __construct(private readonly EstimationService $estimationService)
    {
    }

    public function show(string $employeeId): View
    {
        $this->authorize('viewAny', Employee::class);
        $employee = Employee::query()->findOrFail($employeeId);

        $company = app('current_company');

        $historyLogs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('session_number', 1)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $history = $historyLogs->map(function (AttendanceLog $log) use ($employee) {
            $summary = $this->estimationService->dailySummaryFromLog(
                employee: $employee,
                log: $log,
                date: $log->date?->format('Y-m-d'),
            );

            return [
                'date' => $log->date?->format('Y-m-d'),
                'check_in' => $log->check_in?->setTimezone(app('current_company')->timezone)->format('H:i'),
                'check_out' => $log->check_out?->setTimezone(app('current_company')->timezone)->format('H:i'),
                'hours_worked' => $summary['hours_worked'] ?? 0.0,
                'total_estimated' => $summary['total_estimated'] ?? 0.0,
                'currency' => $summary['currency'] ?? app('current_company')->currency,
                'status' => $log->status ?? 'absent',
            ];
        })->values();

        $defaultTo = now('UTC')->setTimezone($company->timezone)->toDateString();
        $defaultFrom = now('UTC')->setTimezone($company->timezone)->subDays(7)->toDateString();

        return view('employees.show', [
            'company' => $company,
            'employee' => $employee,
            'history' => $history,
            'defaultFrom' => $defaultFrom,
            'defaultTo' => $defaultTo,
        ]);
    }

    public function quickEstimate(Request $request, string $employeeId): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);
        $employee = Employee::query()->findOrFail($employeeId);

        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $validated['from'],
            to: $validated['to'],
        );

        return new JsonResponse(['data' => $estimate]);
    }

    public function receipt(Request $request, string $employeeId): Response
    {
        $this->authorize('viewAny', Employee::class);
        $employee = Employee::query()->findOrFail($employeeId);

        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $validated['from'],
            to: $validated['to'],
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
            $validated['from'],
            $validated['to']
        );

        return $pdf->download($fileName);
    }
}
