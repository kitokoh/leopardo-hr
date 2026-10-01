<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttendanceTodayResource;
use App\Modules\HR\Interfaces\Api\V1\Requests\OwnAttendanceAnomaliesRequest;
use App\Modules\Planning\Infrastructure\Services\EstimationService;
use App\Shared\Contracts\Attendance\AttendanceAnomalySummarizer;
use App\Shared\Contracts\Attendance\AttendanceLogReader;
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
        private readonly AttendanceLogReader $attendanceLogs,
        private readonly AttendanceAnomalySummarizer $anomalySummarizer,
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
        // La règle date_format:Y-m-d garantit une date réelle ; si Carbon
        // échoue malgré tout (strict mode), repli sûr sur « aujourd'hui »
        // (même sémantique que l'absence de paramètre) plutôt qu'un 500.
        $parsedDate = is_string($date) && $date !== ''
            ? Carbon::createFromFormat('Y-m-d', $date, $company->timezone)
            : null;
        $dateLocal = $parsedDate instanceof Carbon
            ? $parsedDate->startOfDay()
            : now('UTC')->setTimezone($company->timezone)->startOfDay();

        $dateKey = $dateLocal->toDateString();

        // #8299 (BOS-023 cycle 3) : lecture via le contrat Shared — requête
        // reprise à l'identique côté adapter Attendance (colonnes, tris).
        $log = $this->attendanceLogs->latestLogForEmployeeOnDate($employee->id, $dateKey);

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
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
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
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) ($validated['year'] ?? $today->format('Y'));
        $month = (int) ($validated['month'] ?? $today->format('m'));

        // year/month sont validés (integer + bornes) ; garde instanceof
        // défensive — repli sur le mois courant plutôt qu'un 500.
        $firstOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, $company->timezone);
        if (! $firstOfMonth instanceof Carbon) {
            $firstOfMonth = now($company->timezone);
        }
        $from = $firstOfMonth->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $from->toDateString(),
            to: $to->toDateString(),
        );

        return new JsonResponse([
            'data' => array_merge($estimate, [
                'year' => $year,
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
    public function attendanceAnomalies(OwnAttendanceAnomaliesRequest $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        // #8299 (BOS-023 cycle 3) : autorisation `viewOwnAnomalies` et merge
        // forcé de employee_id exécutés côté adapter Attendance (séquence
        // historique identique, 403/422 inchangés).
        return new JsonResponse(
            $this->anomalySummarizer->summarizeOwnAnomalies($employee, $request->validated())
        );
    }
}
