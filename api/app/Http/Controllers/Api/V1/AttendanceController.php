<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\DTOs\CheckInDTO;
use App\Http\Requests\Api\V1\Attendance\AttendanceIndexRequest;
use App\Http\Requests\Api\V1\Attendance\AttendanceTodayRequest;
use App\Http\Requests\Api\V1\Attendance\CheckInRequest;
use App\Http\Requests\Api\V1\Attendance\CheckOutRequest;
use App\Http\Resources\Api\V1\AttendanceLogResource;
use App\Http\Resources\Api\V1\AttendanceTodayResource;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $company = app('current_company');
        $today = now('UTC')->setTimezone($company->timezone)->toDateString();

        $employeeId = $request->validated('employee_id');

        if ($employeeId) {
            $target = Employee::query()->findOrFail($employeeId);
            $this->authorize('viewForEmployee', [AttendanceLog::class, $target]);

            $log = AttendanceLog::query()
                ->where('employee_id', $target->id)
                ->where('date', $today)
                ->where('session_number', 1)
                ->orderByDesc('id')
                ->first();

            return new JsonResponse([
                'data' => [
                    'mode' => 'single',
                    'item' => new AttendanceTodayResource($target, $log, $company->timezone),
                ],
            ]);
        }

        if ($actor->isManager()) {
            $this->authorize('viewAny', AttendanceLog::class);
            $perPage = $request->integer('per_page', 50);

            $paginator = Employee::query()
                ->select([
                    'id',
                    'company_id',
                    'matricule',
                    'first_name',
                    'last_name',
                    'email',
                    'role',
                    'status',
                    'salary_type',
                    'salary_base',
                    'hourly_rate',
                ])
                ->where('status', 'active')
                ->orderBy('id')
                ->paginate(max(1, min(100, $perPage)));

            $employees = collect($paginator->items());
            $employeeIds = $employees->pluck('id')->all();

            $logsByEmployee = AttendanceLog::query()
                ->select([
                    'id',
                    'company_id',
                    'employee_id',
                    'date',
                    'check_in',
                    'check_out',
                    'hours_worked',
                    'overtime_hours',
                    'status',
                ])
                ->where('date', $today)
                ->where('session_number', 1)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->keyBy('employee_id');

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
            ->where('session_number', 1)
            ->orderByDesc('id')
            ->first();

        return new JsonResponse([
            'data' => [
                'mode' => 'single',
                'item' => new AttendanceTodayResource($actor, $log, $company->timezone),
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
            ->select(['id', 'employee_id', 'date', 'check_in', 'check_out', 'hours_worked', 'overtime_hours', 'status', 'method', 'source_device_code', 'late_minutes'])
            ->orderByDesc('date')
            ->orderByDesc('id');

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

        $perPage = $validated['per_page'] ?? 20;

        $paginator = $query->paginate($perPage);

        return AttendanceLogResource::collection($paginator)->response();

    }

    public function update(Request $request, AttendanceLog $attendanceLog): JsonResponse
    {
        $this->authorize('update', $attendanceLog);

        $validated = $request->validate([
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
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

        $attendanceLog->fill([
            'check_in' => $effectiveCheckIn,
            'check_out' => $effectiveCheckOut,
            'method' => 'manual',
            'corrected_by' => $request->user()->id,
            'correction_note' => $validated['notes'] ?? $attendanceLog->correction_note,
        ]);

        $attendanceLog = $this->attendanceService->recalculateLog($attendanceLog);

        return (new AttendanceLogResource($attendanceLog))->response();
    }

}
