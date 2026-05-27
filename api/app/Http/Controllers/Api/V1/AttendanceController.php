<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CheckInDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Attendance\AttendanceAnomaliesRequest;
use App\Http\Requests\Api\V1\Attendance\AttendanceIndexRequest;
use App\Http\Requests\Api\V1\Attendance\AttendanceMonthlyReportRequest;
use App\Http\Requests\Api\V1\Attendance\AttendanceTodayRequest;
use App\Http\Requests\Api\V1\Attendance\CheckInRequest;
use App\Http\Requests\Api\V1\Attendance\CheckOutRequest;
use App\Http\Resources\Api\V1\AttendanceLogResource;
use App\Http\Resources\Api\V1\AttendanceTodayResource;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceAnomalyService;
use App\Services\AttendanceMonthlyReportService;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $this->authorize('checkIn', AttendanceLog::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $log = $this->attendanceService->checkIn(
            employee: $employee,
            dto: CheckInDTO::fromRequest($request),
        );

        return (new AttendanceLogResource($log))
            ->response()
            ->setStatusCode(201);
    }

    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $this->authorize('checkOut', AttendanceLog::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $log = $this->attendanceService->checkOut(
            employee: $employee,
            dto: CheckInDTO::fromRequest($request),
        );

        return (new AttendanceLogResource($log))->response();
    }

    public function today(AttendanceTodayRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $company = currentCompany();
        $today = now('UTC')->setTimezone($company->timezone)->toDateString();

        $employeeId = $request->validated('employee_id');

        if ($employeeId) {
            $target = Employee::query()->findOrFail($employeeId);
            $this->authorize('viewForEmployee', [AttendanceLog::class, $target]);

            $log = AttendanceLog::query()
                ->where('employee_id', $target->id)
                ->where('date', $today)
                ->orderByRaw('CASE WHEN check_out IS NULL THEN 1 ELSE 0 END DESC')
                ->orderByDesc('session_number')
                ->first();
            $sessions = $this->dailySessions($target, $today);

            return new JsonResponse([
                'data' => [
                    'mode' => 'single',
                    'item' => new AttendanceTodayResource($target, $log, $company->timezone),
                    'sessions' => AttendanceLogResource::collection($sessions)->resolve($request),
                    'summary' => $this->dailySessionSummary($sessions),
                ],
            ]);
        }

        if ($actor->isManager()) {
            $this->authorize('viewAny', AttendanceLog::class);
            $perPage = $request->integer('per_page', 50);

            $paginator = Employee::query()
                ->select(['id', 'company_id', 'first_name', 'last_name', 'email', 'role', 'status'])
                ->where('company_id', $actor->company_id)
                ->where('status', 'active')
                ->orderBy('id')
                ->paginate(max(1, min(100, $perPage)));

            $employees = collect($paginator->items());
            $employeeIds = $employees->pluck('id')->all();

            $logsByEmployee = AttendanceLog::query()
                ->select(['id', 'employee_id', 'date', 'session_number', 'check_in', 'check_out', 'hours_worked', 'overtime_hours', 'status', 'method', 'work_type', 'punch_note', 'punch_meta', 'late_minutes'])
                ->where('date', $today)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->groupBy('employee_id')
                ->map(fn ($logs) => $logs
                    ->sortByDesc(fn (AttendanceLog $log) => ($log->check_out === null ? 100000 : 0) + (int) $log->session_number)
                    ->first());

            $timezone = $company->timezone;

            $data = $employees->map(function (Employee $employee) use ($logsByEmployee, $timezone) {
                return new AttendanceTodayResource($employee, $logsByEmployee->get($employee->id), $timezone);
            })->values();

            return new JsonResponse([
                'data' => [
                    'mode' => 'collection',
                    'items' => $data,
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                    ],
                ],
            ]);
        }

        $this->authorize('viewForEmployee', [AttendanceLog::class, $actor]);

        $log = AttendanceLog::query()
            ->where('employee_id', $actor->id)
            ->where('date', $today)
            ->orderByRaw('CASE WHEN check_out IS NULL THEN 1 ELSE 0 END DESC')
            ->orderByDesc('session_number')
            ->first();
        $sessions = $this->dailySessions($actor, $today);

        return new JsonResponse([
            'data' => [
                'mode' => 'single',
                'item' => new AttendanceTodayResource($actor, $log, $company->timezone),
                'sessions' => AttendanceLogResource::collection($sessions)->resolve($request),
                'summary' => $this->dailySessionSummary($sessions),
            ],
        ]);
    }

    public function index(AttendanceIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validated();

        $employeeId = $validated['employee_id'] ?? null;
        if ($employeeId) {
            $target = Employee::query()->findOrFail($employeeId);
            $this->authorize('viewForEmployee', [AttendanceLog::class, $target]);
        } else {
            $target = $actor;
            if ($actor->isManager()) {
                $this->authorize('viewAny', AttendanceLog::class);
                $target = null;
            } else {
                $this->authorize('viewForEmployee', [AttendanceLog::class, $actor]);
            }
        }

        $query = AttendanceLog::query()
            ->with(['employee:id,first_name,last_name,matricule,photo_path'])
            ->select(['id', 'company_id', 'employee_id', 'date', 'session_number', 'check_in', 'check_out', 'hours_worked', 'overtime_hours', 'status', 'method', 'work_type', 'punch_note', 'punch_meta', 'source_device_code', 'late_minutes']);

        if ($target) {
            $query->where('employee_id', $target->id);
        } else {
            // Manager viewing all employees: scope to own company to prevent cross-tenant data leakage.
            // AttendanceLog has no global company scope, so we must add the WHERE clause explicitly.
            $query->where('company_id', $actor->company_id);
        }

        if (! empty($validated['date_from'])) {
            $query->where('date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('date', '<=', $validated['date_to']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $sortBy = (string) ($validated['sort_by'] ?? 'date');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');

        $paginator = $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);

        return AttendanceLogResource::collection($paginator)->response();

    }

    public function anomalies(AttendanceAnomaliesRequest $request, AttendanceAnomalyService $anomalyService): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('viewAny', AttendanceLog::class);

        return new JsonResponse(
            $anomalyService->summarize($actor->company_id, $request->validated())
        );
    }

    public function monthlyReport(
        AttendanceMonthlyReportRequest $request,
        AttendanceMonthlyReportService $reportService,
    ): JsonResponse|Response {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('viewAny', AttendanceLog::class);

        $company = currentCompany();
        $validated = $request->validated();
        $month = $validated['month'] ?? now($company->timezone)->format('Y-m');
        $format = $validated['format'] ?? 'json';
        $report = $reportService->build($company, $month);

        return match ($format) {
            'csv' => $reportService->toCsv($report),
            'pdf' => $reportService->toPdf($report),
            default => new JsonResponse($report),
        };
    }

    public function requestCorrection(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'attendance_log_id' => ['nullable', 'integer', 'exists:attendance_logs,id'],
            'date' => ['required', 'date'],
            'requested_check_in' => ['required', 'date'],
            'requested_check_out' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $company = currentCompany();
        $timezone = $company->timezone;
        $requestedCheckIn = Carbon::parse($validated['requested_check_in'])->setTimezone($timezone);
        $requestedCheckOut = isset($validated['requested_check_out'])
            ? Carbon::parse($validated['requested_check_out'])->setTimezone($timezone)
            : null;

        if ($requestedCheckIn->isFuture() || ($requestedCheckOut !== null && $requestedCheckOut->isFuture())) {
            throw ValidationException::withMessages([
                'requested_check_in' => ['Impossible de demander une correction avec une heure future.'],
            ]);
        }

        if ($requestedCheckOut !== null && $requestedCheckOut->lessThanOrEqualTo($requestedCheckIn)) {
            throw ValidationException::withMessages([
                'requested_check_out' => ['L\'heure de depart doit etre posterieure a l\'heure d\'arrivee.'],
            ]);
        }

        $log = null;
        if (! empty($validated['attendance_log_id'])) {
            $log = AttendanceLog::query()
                ->where('id', $validated['attendance_log_id'])
                ->where('employee_id', $actor->id)
                ->firstOrFail();
        }

        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $actor->company_id,
            'employee_id' => $actor->id,
            'attendance_log_id' => $log?->id,
            'date' => $validated['date'],
            'requested_check_in' => $requestedCheckIn,
            'requested_check_out' => $requestedCheckOut,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => [
                'id' => $correction->id,
                'status' => $correction->status,
                'date' => $correction->date?->format('Y-m-d'),
                'requested_check_in' => $correction->requested_check_in?->toIso8601String(),
                'requested_check_out' => $correction->requested_check_out?->toIso8601String(),
            ],
            'message' => 'Demande de modification transmise au RH.',
        ], 201);
    }

    public function update(Request $request, AttendanceLog $attendanceLog): JsonResponse
    {
        $this->authorize('update', $attendanceLog);

        $validated = $request->validate([
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
        ]);

        $effectiveCheckIn = array_key_exists('check_in', $validated)
            ? $validated['check_in']
            : $attendanceLog->check_in;
        $effectiveCheckOut = array_key_exists('check_out', $validated)
            ? $validated['check_out']
            : $attendanceLog->check_out;

        if ($effectiveCheckOut !== null && $effectiveCheckIn === null) {
            throw ValidationException::withMessages([
                'check_out' => ['Le départ manuel nécessite une heure d\'arrivée.'],
            ]);
        }

        if ($effectiveCheckIn !== null && $effectiveCheckOut !== null && $effectiveCheckOut <= $effectiveCheckIn) {
            throw ValidationException::withMessages([
                'check_out' => ['L\'heure de départ doit être postérieure à l\'heure d\'arrivée.'],
            ]);
        }

        /** @var Employee $user */
        $user = $request->user();

        $attendanceLog->fill([
            'check_in' => $effectiveCheckIn,
            'check_out' => $effectiveCheckOut,
            'method' => 'manual',
            'work_type' => $validated['work_type'] ?? $attendanceLog->work_type ?? 'normal',
            'corrected_by' => $user->id,
            'correction_note' => $validated['notes'] ?? $attendanceLog->correction_note,
        ]);

        $attendanceLog = $this->attendanceService->recalculateLog($attendanceLog);

        return (new AttendanceLogResource($attendanceLog))->response();
    }

    /**
     * @return EloquentCollection<int, AttendanceLog>
     */
    private function dailySessions(Employee $employee, string $date): EloquentCollection
    {
        return AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderBy('session_number')
            ->get();
    }

    /**
     * @param  iterable<int, AttendanceLog>  $sessions
     * @return array<string, mixed>
     */
    private function dailySessionSummary(iterable $sessions): array
    {
        $totalHours = 0.0;
        $overtimeHours = 0.0;
        $lateMinutes = 0;
        $breakMinutes = 0;
        $previousCheckout = null;
        $openSession = null;
        $count = 0;

        foreach ($sessions as $session) {
            $count++;
            $totalHours += (float) ($session->hours_worked ?? 0);
            $overtimeHours += (float) ($session->overtime_hours ?? 0);
            $lateMinutes += (int) ($session->late_minutes ?? 0);

            if ($previousCheckout !== null && $session->check_in !== null) {
                $breakMinutes += max(0, $previousCheckout->diffInMinutes($session->check_in, false));
            }

            if ($session->check_out !== null) {
                $previousCheckout = $session->check_out;
            } else {
                $openSession = $session;
            }
        }

        return [
            'sessions_count' => $count,
            'is_working' => $openSession !== null,
            'current_session_id' => $openSession?->id,
            'current_work_type' => $openSession?->work_type,
            'total_hours_worked' => round($totalHours, 2),
            'total_overtime_hours' => round($overtimeHours, 2),
            'break_minutes' => $breakMinutes,
            'late_minutes' => $lateMinutes,
        ];
    }
}
