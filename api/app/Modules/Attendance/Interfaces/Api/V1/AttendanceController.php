<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttendanceLogResource;
use App\Http\Resources\Api\V1\AttendanceTodayResource;
use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceAnomalyService;
use App\Modules\Attendance\Infrastructure\Services\AttendanceMonthlyReportService;
use App\Modules\Attendance\Infrastructure\Services\AttendancePeriodClosureService;
use App\Modules\Attendance\Infrastructure\Services\AttendanceRegularityService;
use App\Modules\Attendance\Infrastructure\Services\AttendanceReportService;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceAnomaliesRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceIndexRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceRegularityRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceReportRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\AttendanceTodayRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\CheckInRequest;
use App\Modules\Attendance\Interfaces\Api\V1\Requests\CheckOutRequest;
use App\Modules\Planning\Infrastructure\Services\EstimationService;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly AttendancePeriodClosureService $periodClosures,
    ) {}

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
            $estimatedSummary = app(EstimationService::class)->dailySummaryFromLogs($target, $sessions, $today);

            return new JsonResponse([
                'data' => [
                    'mode' => 'single',
                    'item' => new AttendanceTodayResource($target, $log, $company->timezone, $estimatedSummary),
                    'sessions' => AttendanceLogResource::collection($sessions)->resolve($request),
                    'summary' => $this->dailySessionSummary($sessions),
                ],
            ]);
        }

        if ($actor->isManager()) {
            $this->authorize('viewAny', AttendanceLog::class);
            $perPage = max(1, min(100, $request->integer('per_page', 50)));

            $paginator = Employee::query()
                ->select(['id', 'company_id', 'department_id', 'first_name', 'last_name', 'email', 'role', 'status'])
                ->where('company_id', $actor->company_id)
                ->where('status', 'active')
                ->when(
                    $actor->isTeamScoped(),
                    fn ($query) => $query->visibleToManager($actor)
                )
                ->orderBy('id')
                ->paginate(max(1, min(100, $perPage)));

            $employees = collect($paginator->items());
            $employeeIds = $employees->pluck('id')->all();

            $logsByEmployee = AttendanceLog::query()
                ->select(['id', 'employee_id', 'date', 'session_number', 'check_in', 'check_out', 'hours_worked', 'overtime_hours', 'status', 'method', 'work_type', 'punch_note', 'punch_meta', 'late_minutes'])
                ->where('date', $today)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->groupBy('employee_id');

            $timezone = $company->timezone;

            $estimationService = app(EstimationService::class);

            $data = $employees->map(function (Employee $employee) use ($logsByEmployee, $timezone, $today, $estimationService) {
                $logs = $logsByEmployee->get($employee->id, collect());
                $log = $logs
                    ->sortByDesc(fn (AttendanceLog $log) => ($log->check_out === null ? 100000 : 0) + (int) $log->session_number)
                    ->first();
                $summary = $estimationService->dailySummaryFromLogs($employee, $logs, $today);

                return new AttendanceTodayResource($employee, $log, $timezone, $summary);
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
        $estimatedSummary = app(EstimationService::class)->dailySummaryFromLogs($actor, $sessions, $today);

        return new JsonResponse([
            'data' => [
                'mode' => 'single',
                'item' => new AttendanceTodayResource($actor, $log, $company->timezone, $estimatedSummary),
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

            if ($actor->isTeamScoped()) {
                // manager_role=dept is scoped to their own department only (PA2-SEC-002);
                // manager_role=superviseur is scoped to their own assigned team (PA2-SEC-003).
                $scopedEmployeeIds = Employee::query()
                    ->where('company_id', $actor->company_id)
                    ->visibleToManager($actor)
                    ->pluck('id');
                $query->whereIn('employee_id', $scopedEmployeeIds);
            }
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

        $perPage = max(1, min(100, (int) ($validated['per_page'] ?? 20)));
        $sortBy = (string) ($validated['sort_by'] ?? 'date');
        $sortDir = (string) ($validated['sort_dir'] ?? 'desc');

        $paginator = $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage);

        return AttendanceLogResource::collection($paginator)->response();

    }

    public function regularity(AttendanceRegularityRequest $request, AttendanceRegularityService $regularityService): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $employeeId = $validated['employee_id'] ?? null;

        $target = $employeeId ? Employee::query()->findOrFail($employeeId) : $actor;
        $this->authorize('viewForEmployee', [AttendanceLog::class, $target]);

        $company = currentCompany();
        $dateTo = $validated['date_to'] ?? now('UTC')->setTimezone($company->timezone)->toDateString();
        $dateFrom = $validated['date_from'] ?? Carbon::parse($dateTo)->subDays(30)->toDateString();

        return new JsonResponse($regularityService->summarize($target, $dateFrom, $dateTo));
    }

    public function anomalies(AttendanceAnomaliesRequest $request, AttendanceAnomalyService $anomalyService): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('viewAny', AttendanceLog::class);

        return new JsonResponse(
            $anomalyService->summarize(
                $actor->company_id,
                $request->validated(),
                $actor,
            )
        );
    }

    public function report(
        AttendanceReportRequest $request,
        AttendanceReportService $reportService,
    ): JsonResponse|Response {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('viewAny', AttendanceLog::class);

        $company = currentCompany();
        $validated = $request->validated();
        $period = $validated['period'] ?? AttendanceReportService::PERIOD_MONTH;
        $format = $validated['format'] ?? 'json';
        $report = $reportService->build(
            $company,
            $period,
            $validated,
            $actor,
        );

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

        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'attendance_log_id' => ['nullable', 'integer', 'exists:attendance_logs,id'],
            'date' => ['required', 'date'],
            'requested_check_in' => ['required', 'date'],
            'requested_check_out' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            // Issue #5267 : justificatif optionnel (mêmes règles que les absences).
            'proof' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,heic'],
        ]);

        // Issue #5267 : une période clôturée verrouille les corrections.
        $this->periodClosures->assertPeriodOpen((string) $actor->company_id, Carbon::parse($validated['date']));

        $company = currentCompany();
        $timezone = $company->timezone;
        $requestedCheckIn = Carbon::parse($validated['requested_check_in'])->setTimezone($timezone);
        $requestedCheckOut = isset($validated['requested_check_out'])
            ? Carbon::parse($validated['requested_check_out'])->setTimezone($timezone)
            : null;

        $errors = [];
        if ($requestedCheckIn->isFuture()) {
            $errors['requested_check_in'] = [__('attendance.correction_future_check_in')];
        }
        if ($requestedCheckOut !== null && $requestedCheckOut->isFuture()) {
            $errors['requested_check_out'] = [__('attendance.correction_future_check_out')];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($requestedCheckOut !== null && $requestedCheckOut->lessThanOrEqualTo($requestedCheckIn)) {
            throw ValidationException::withMessages([
                'requested_check_out' => [__('errors.ATTENDANCE_CHECK_OUT_AFTER_CHECK_IN')],
            ]);
        }

        $log = null;
        if (! empty($validated['attendance_log_id'])) {
            $log = AttendanceLog::query()
                ->where('id', $validated['attendance_log_id'])
                ->where('employee_id', $actor->id)
                ->firstOrFail();
        }

        // Issue #5267 : stockage du justificatif sous un chemin scopé entreprise
        // (même pattern que les absences, PA2-MOB-006).
        $proof = $request->file('proof');
        $proofPath = $proof instanceof UploadedFile
            ? $proof->store('attendance-corrections/proofs/'.$actor->company_id, 'local')
            : null;

        $correction = AttendanceCorrectionRequest::query()->create([
            'company_id' => $actor->company_id,
            'employee_id' => $actor->id,
            'attendance_log_id' => $log?->id,
            'date' => $validated['date'],
            'requested_check_in' => $requestedCheckIn,
            'requested_check_out' => $requestedCheckOut,
            'reason' => $validated['reason'],
            'proof_path' => $proofPath,
            'status' => 'pending',
        ]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'attendance_correction_requested',
            'auditable_type' => $correction->getMorphClass(),
            'auditable_id' => $correction->id,
            'old_values' => [],
            'new_values' => [
                'date' => $correction->date->format('Y-m-d'),
                'requested_check_in' => $requestedCheckIn->toIso8601String(),
                'requested_check_out' => $requestedCheckOut?->toIso8601String(),
                'has_proof' => $proofPath !== null,
            ],
        ]);

        return response()->json([
            'data' => [
                'id' => $correction->id,
                'status' => $correction->status,
                'date' => $correction->date->format('Y-m-d'),
                'requested_check_in' => $correction->requested_check_in->toIso8601String(),
                'requested_check_out' => $correction->requested_check_out?->toIso8601String(),
                'proof_url' => $correction->proof_path !== null
                    ? route('attendance.corrections.proof', ['correction' => $correction->id])
                    : null,
            ],
            'message' => __('attendance.correction_transmitted'),
        ], 201);
    }

    public function corrections(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('update', new AttendanceLog(['company_id' => $actor->company_id]));

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected,applied'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = AttendanceCorrectionRequest::query()
            ->with(['employee:id,company_id,first_name,last_name,matricule', 'attendanceLog:id,employee_id,date,session_number,status'])
            ->where('company_id', $actor->company_id)
            ->when($validated['status'] ?? 'pending', fn ($builder, string $status) => $builder->where('status', $status))
            ->when($validated['date_from'] ?? null, fn ($builder, string $date) => $builder->whereDate('date', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($builder, string $date) => $builder->whereDate('date', '<=', $date))
            ->orderByDesc('date')
            ->orderByDesc('id');

        $paginator = $query->paginate(max(1, min(100, (int) ($validated['per_page'] ?? 20))));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (AttendanceCorrectionRequest $correction): array => $this->correctionPayload($correction))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function approveCorrection(Request $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('update', new AttendanceLog(['company_id' => $correction->company_id]));
        $this->ensureCorrectionBelongsToActorCompany($correction, $actor);

        // Issue #5267 : une période clôturée verrouille les décisions aussi.
        $this->periodClosures->assertPeriodOpen((string) $correction->company_id, $correction->date);

        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => [__('attendance.correction_already_processed')],
            ]);
        }

        $employee = Employee::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($correction->employee_id);

        $log = $correction->attendanceLog;
        if (! $log) {
            $sessionNumber = ((int) AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', $correction->date)
                ->max('session_number')) + 1;

            $log = new AttendanceLog([
                'company_id' => $actor->company_id,
                'employee_id' => $employee->id,
                'schedule_id' => $employee->schedule_id,
                'date' => $correction->date,
                'session_number' => $sessionNumber,
                'method' => 'manual',
                'work_type' => 'normal',
            ]);
        }

        $log->fill([
            'check_in' => $correction->requested_check_in,
            'check_out' => $correction->requested_check_out,
            'method' => 'manual',
            'corrected_by' => $actor->id,
            'correction_note' => $correction->reason,
        ]);

        $log = $this->attendanceService->recalculateLog($log);

        $correction->forceFill([
            'attendance_log_id' => $log->id,
            'status' => 'applied',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        // Issue #5267 : décision tracée (audit trail).
        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'attendance_correction_approved',
            'auditable_type' => $correction->getMorphClass(),
            'auditable_id' => $correction->id,
            'old_values' => ['status' => 'pending'],
            'new_values' => [
                'status' => 'applied',
                'attendance_log_id' => $log->id,
                'check_in' => $log->check_in?->toIso8601String(),
                'check_out' => $log->check_out?->toIso8601String(),
            ],
        ]);

        $correction->load(['employee', 'attendanceLog']);

        return response()->json([
            'data' => $this->correctionPayload($correction),
            'attendance_log' => (new AttendanceLogResource($log))->resolve($request),
            'message' => __('attendance.correction_applied'),
        ]);
    }

    public function rejectCorrection(Request $request, AttendanceCorrectionRequest $correction): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('update', new AttendanceLog(['company_id' => $correction->company_id]));
        $this->ensureCorrectionBelongsToActorCompany($correction, $actor);

        // Issue #5267 : une période clôturée verrouille les décisions aussi.
        $this->periodClosures->assertPeriodOpen((string) $correction->company_id, $correction->date);

        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => [__('attendance.correction_already_processed')],
            ]);
        }

        $correction->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ])->save();

        // Issue #5267 : décision tracée (audit trail).
        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'attendance_correction_rejected',
            'auditable_type' => $correction->getMorphClass(),
            'auditable_id' => $correction->id,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'rejected'],
        ]);

        $correction->load(['employee', 'attendanceLog']);

        return response()->json([
            'data' => $this->correctionPayload($correction),
            'message' => __('attendance.correction_rejected'),
        ]);
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
                'check_out' => [__('attendance.manual_checkout_requires_check_in')],
            ]);
        }

        if ($effectiveCheckIn !== null && $effectiveCheckOut !== null && $effectiveCheckOut <= $effectiveCheckIn) {
            throw ValidationException::withMessages([
                'check_out' => [__('attendance.checkout_after_checkin')],
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
     * GET /api/v1/attendance/{attendanceLog}/punch-photo
     * Retourne la photo de pointage associee a un log (mode photo_required).
     * Accessible a l'employe concerne et aux managers autorises a voir ce log.
     */
    public function punchPhoto(Request $request, AttendanceLog $attendanceLog): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $target = Employee::query()->findOrFail($attendanceLog->employee_id);
        $this->authorize('viewForEmployee', [AttendanceLog::class, $target]);

        if (! $attendanceLog->punch_photo_path) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($attendanceLog->punch_photo_path)) {
            abort(404);
        }

        return $disk->download($attendanceLog->punch_photo_path);
    }

    private function ensureCorrectionBelongsToActorCompany(AttendanceCorrectionRequest $correction, Employee $actor): void
    {
        if ($correction->company_id !== $actor->company_id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function correctionPayload(AttendanceCorrectionRequest $correction): array
    {
        return [
            'id' => $correction->id,
            'company_id' => $correction->company_id,
            'employee_id' => $correction->employee_id,
            'attendance_log_id' => $correction->attendance_log_id,
            'employee' => $correction->relationLoaded('employee') && $correction->employee ? [
                'id' => $correction->employee->id,
                'name' => trim(($correction->employee->first_name ?? '').' '.($correction->employee->last_name ?? '')),
                'matricule' => $correction->employee->matricule,
            ] : null,
            'date' => $correction->date->format('Y-m-d'),
            'requested_check_in' => $correction->requested_check_in->toIso8601String(),
            'requested_check_out' => $correction->requested_check_out?->toIso8601String(),
            'reason' => $correction->reason,
            // Issue #5267 : justificatif + signalement anti-fraude.
            'proof_url' => $correction->proof_path !== null
                ? route('attendance.corrections.proof', ['correction' => $correction->id])
                : null,
            'anomaly' => $this->correctionAnomaly($correction),
            'status' => $correction->status,
            'reviewed_by' => $correction->reviewed_by,
            'reviewed_at' => $correction->reviewed_at?->toIso8601String(),
        ];
    }

    /**
     * Issue #5267 — anti-fraude : signale une correction dont les horaires
     * demandés contredisent une session géo VALIDÉE (écart > 15 min).
     *
     * @return array{flagged: bool, reason: string|null, session: array<string, mixed>|null}|null
     */
    private function correctionAnomaly(AttendanceCorrectionRequest $correction): ?array
    {
        $session = GeoAttendanceSession::query()
            ->where('company_id', $correction->company_id)
            ->where('employee_id', $correction->employee_id)
            ->where('status', 'approved')
            ->whereDate('started_at', $correction->date->toDateString())
            ->first();

        if ($session === null) {
            return null;
        }

        $thresholdMinutes = 15;
        $conflicts = [];

        if (abs($correction->requested_check_in->diffInMinutes($session->started_at)) > $thresholdMinutes) {
            $conflicts[] = 'check_in';
        }
        if ($session->ended_at !== null && $correction->requested_check_out !== null
            && abs($correction->requested_check_out->diffInMinutes($session->ended_at)) > $thresholdMinutes) {
            $conflicts[] = 'check_out';
        }

        if ($conflicts === []) {
            return null;
        }

        return [
            'flagged' => true,
            'reason' => 'geo_session_conflict',
            'session' => [
                'id' => $session->id,
                'started_at' => $session->started_at->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Issue #5267 — téléchargement du justificatif d'une demande de
     * correction. Réservé au propriétaire de la demande et aux managers du
     * tenant (jamais de fuite cross-tenant).
     */
    public function downloadProofCorrection(Request $request, AttendanceCorrectionRequest $correction): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->ensureCorrectionBelongsToActorCompany($correction, $actor);

        if ($actor->id !== $correction->employee_id && ! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        if ($correction->proof_path === null) {
            abort(404, 'NO_PROOF_ATTACHED');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($correction->proof_path)) {
            abort(404, 'NO_PROOF_ATTACHED');
        }

        return $disk->download($correction->proof_path);
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
