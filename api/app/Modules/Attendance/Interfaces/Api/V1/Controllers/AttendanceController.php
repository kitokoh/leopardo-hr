<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttendanceLogResource;
use App\Http\Resources\Api\V1\AttendanceTodayResource;
use App\Modules\Attendance\Application\Actions\ApproveCorrectionRequest;
use App\Modules\Attendance\Application\Actions\GetAttendanceToday;
use App\Modules\Attendance\Application\Actions\RejectCorrectionRequest;
use App\Modules\Attendance\Application\Actions\SubmitCorrectionRequest;
use App\Modules\Attendance\Application\Actions\UpdateAttendanceLog;
use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Application\Support\AttendanceCorrectionPresenter;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceAnomalyService;
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
        private readonly SubmitCorrectionRequest $submitCorrectionAction,
        private readonly ApproveCorrectionRequest $approveCorrectionAction,
        private readonly RejectCorrectionRequest $rejectCorrectionAction,
        private readonly UpdateAttendanceLog $updateAttendanceLogAction,
        private readonly GetAttendanceToday $getAttendanceToday,
        private readonly AttendanceCorrectionPresenter $presenter,
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
            /** @var Employee $target */
            $target = Employee::query()->findOrFail($employeeId);
            $this->authorize('viewForEmployee', [AttendanceLog::class, $target]);

            $result = $this->getAttendanceToday->forEmployee($target, $today);

            return new JsonResponse([
                'data' => [
                    'mode' => 'single',
                    'item' => new AttendanceTodayResource($target, $result['log'], $company->timezone, $result['estimation']),
                    'sessions' => AttendanceLogResource::collection($result['sessions'])->resolve($request),
                    'summary' => $result['summary'],
                ],
            ]);
        }

        if ($actor->isManager()) {
            $this->authorize('viewAny', AttendanceLog::class);

            $perPage = max(1, min(100, $request->integer('per_page', 50)));
            $result = $this->getAttendanceToday->forManagerScope($actor, $today, $perPage);

            $items = collect($result['items'])->map(
                fn (array $row) => new AttendanceTodayResource($row['employee'], $row['log'], $company->timezone, $row['summary'])
            )->values();

            return new JsonResponse([
                'data' => [
                    'mode' => 'collection',
                    'items' => $items,
                    'meta' => $result['meta'],
                ],
            ]);
        }

        $this->authorize('viewForEmployee', [AttendanceLog::class, $actor]);

        $result = $this->getAttendanceToday->forEmployee($actor, $today);

        return new JsonResponse([
            'data' => [
                'mode' => 'single',
                'item' => new AttendanceTodayResource($actor, $result['log'], $company->timezone, $result['estimation']),
                'sessions' => AttendanceLogResource::collection($result['sessions'])->resolve($request),
                'summary' => $result['summary'],
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

        $proof = $request->file('proof');

        $correction = $this->submitCorrectionAction->execute(
            $actor,
            $validated,
            $proof instanceof UploadedFile ? $proof : null,
        );

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
            ->where('company_id', $actor->company_id)
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

        $log = $this->approveCorrectionAction->execute($correction, $actor);

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

        $this->rejectCorrectionAction->execute($correction, $actor);

        return response()->json([
            'data' => $this->correctionPayload($correction),
            'message' => __('attendance.correction_rejected'),
        ]);
    }

    public function update(Request $request, AttendanceLog $attendanceLog): JsonResponse
    {
        // DEP-BC05 (#5881) : fail-closed cross-tenant — AttendanceLog n'a pas de
        // scope global ; sans ce garde, un manager pouvait modifier un log d'un
        // autre tenant (l'authorize ne vérifie que le rôle). 404 (convention
        // anti-existence, pattern show/approve/reject).
        /** @var Employee $actor */
        $actor = $request->user();
        if ($attendanceLog->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $attendanceLog);

        $validated = $request->validate([
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'work_type' => ['nullable', 'string', 'in:normal,overtime,break,resume,mission,travel,training,other'],
        ]);

        $attendanceLog = $this->updateAttendanceLogAction->execute($attendanceLog, $actor, $validated);

        return (new AttendanceLogResource($attendanceLog))->response();
    }

    /**
     * GET /api/v1/attendance/attendance-logs/{attendanceLog}/punch-photo (alias déprécié : /attendance/{attendanceLog}/punch-photo #5538)
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
        return $this->presenter->payload($correction);
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

        if ($actor->id !== $correction->employee_id && ! $actor->hasManagerRole('principal', 'rh')) {
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
}
