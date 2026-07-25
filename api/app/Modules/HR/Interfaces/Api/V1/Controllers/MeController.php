<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttendanceTodayResource;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Infrastructure\Services\AttendanceAnomalyService;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceAnomaliesRequest;
use App\Modules\Planning\Infrastructure\Services\EstimationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * MeController — self-service endpoints for the authenticated employee.
 *
 * Migrated from App\Http\Controllers\Api\V1\MeController.
 * Employees consult their own hours and estimates without needing their own ID.
 * Manager-scoped /employees/{id}/* routes stay protected by the Employee policy.
 */
class MeController extends Controller
{
    public function __construct(
        private readonly EstimationService $estimationService,
    ) {}

    public function dailySummary(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $company = currentCompany();
        $date = $request->input('date');
        $dateLocal = $date
            ? Carbon::createFromFormat('Y-m-d', $date, $company->timezone)->startOfDay()
            : now('UTC')->setTimezone($company->timezone)->startOfDay();

        $dateKey = $dateLocal->toDateString();

        $log = AttendanceLog::query()
            ->select(['id', 'employee_id', 'date', 'session_number', 'check_in', 'check_out', 'hours_worked', 'overtime_hours', 'status', 'work_type', 'late_minutes'])
            ->where('employee_id', $employee->id)
            ->where('date', $dateKey)
            ->orderByRaw('CASE WHEN check_out IS NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('session_number')
            ->first();

        $summary = $this->estimationService->dailySummary($employee, $dateKey);

        return (new AttendanceTodayResource($employee, $log, $company->timezone, $summary))->response();
    }

    public function quickEstimate(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $company = currentCompany();
        $today = now('UTC')->setTimezone($company->timezone)->startOfDay();
        $defaultFrom = $today->copy()->startOfMonth()->toDateString();
        $defaultTo = $today->toDateString();

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $validated['from'] ?? $defaultFrom,
            to: $validated['to'] ?? $defaultTo,
        );

        return new JsonResponse(['data' => $estimate]);
    }

    public function monthlySummary(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $company = currentCompany();
        $today = now('UTC')->setTimezone($company->timezone)->startOfDay();

        $validated = $request->validate([
            'year'  => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year  = (int) ($validated['year'] ?? $today->format('Y'));
        $month = (int) ($validated['month'] ?? $today->format('m'));

        $from = Carbon::create($year, $month, 1, 0, 0, 0, $company->timezone)->startOfMonth();
        $to   = $from->copy()->endOfMonth();

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $from->toDateString(),
            to: $to->toDateString(),
        );

        return new JsonResponse([
            'data' => array_merge($estimate, [
                'year'  => $year,
                'month' => $month,
            ]),
        ]);
    }

    /**
     * PA2-ATT-004 - Self-service anomaly view: an employee can see anomalies
     * detected on their own attendance logs (late arrivals, missing
     * check-outs, excessive overtime, etc.) without needing manager
     * privileges. Always force-scoped to the caller's own employee_id so a
     * regular employee can never read another employee's anomalies through
     * this endpoint.
     */
    public function attendanceAnomalies(AttendanceAnomaliesRequest $request, AttendanceAnomalyService $anomalyService): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $this->authorize('viewOwnAnomalies', AttendanceLog::class);

        $filters = array_merge($request->validated(), ['employee_id' => $employee->id]);

        return new JsonResponse(
            $anomalyService->summarize($employee->company_id, $filters, null)
        );
    }
}

