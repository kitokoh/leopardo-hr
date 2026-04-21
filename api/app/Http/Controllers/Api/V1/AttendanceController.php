<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Attendance\AttendanceIndexRequest;
use App\Http\Requests\Api\V1\Attendance\AttendanceTodayRequest;
use App\Http\Requests\Api\V1\Attendance\CheckInRequest;
use App\Http\Requests\Api\V1\Attendance\CheckOutRequest;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $this->authorize('checkIn', AttendanceLog::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $log = $this->attendanceService->checkIn(
            employee: $employee,
            gpsLat: $request->validated('gps_lat'),
            gpsLng: $request->validated('gps_lng'),
        );

        return new JsonResponse([
            'data' => $this->serializeLog($log),
        ], 201);
    }

    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $this->authorize('checkOut', AttendanceLog::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $log = $this->attendanceService->checkOut(
            employee: $employee,
            gpsLat: $request->validated('gps_lat'),
            gpsLng: $request->validated('gps_lng'),
        );

        return new JsonResponse([
            'data' => $this->serializeLog($log),
        ]);
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
                    'item' => $this->serializeToday($target, $log, $company->timezone),
                ],
            ]);
        }

        if ($actor->isManager()) {
            $this->authorize('viewAny', AttendanceLog::class);
            $perPage = $request->integer('per_page', 50);

            $paginator = Employee::query()
                ->select(['id', 'first_name', 'last_name', 'email', 'role', 'status'])
                ->orderBy('id')
                ->paginate(max(1, min(100, $perPage)));

            $employees = collect($paginator->items());
            $employeeIds = $employees->pluck('id')->all();

            $logsByEmployee = AttendanceLog::query()
                ->select(['id', 'employee_id', 'date', 'check_in', 'check_out', 'hours_worked', 'status'])
                ->where('date', $today)
                ->where('session_number', 1)
                ->whereIn('employee_id', $employeeIds)
                ->get()
                ->keyBy('employee_id');

            $timezone = $company->timezone;

            $data = $employees->map(function (Employee $employee) use ($logsByEmployee, $timezone) {
                return $this->serializeToday($employee, $logsByEmployee->get($employee->id), $timezone);
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
                'item' => $this->serializeToday($actor, $log, $company->timezone),
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
            ->select(['id', 'employee_id', 'date', 'check_in', 'check_out', 'hours_worked', 'overtime_hours', 'status', 'method', 'source_device_code', 'late_minutes'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($target) {
            $query->where('employee_id', $target->id);
        }

        if (! empty($validated['date_from'])) {
            $query->where('date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('date', '<=', $validated['date_to']);
        }

        $perPage = $validated['per_page'] ?? 20;

        $paginator = $query->paginate($perPage);
        $data = collect($paginator->items())->map(fn (AttendanceLog $log) => $this->serializeLog($log))->values();

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function serializeLog(AttendanceLog $log): array
    {
        return [
            'id' => $log->id,
            'employee_id' => $log->employee_id,
            'date' => $log->date?->format('Y-m-d'),
            'check_in' => $log->check_in?->toIso8601String(),
            'check_out' => $log->check_out?->toIso8601String(),
            'method' => $log->method,
            'source_device_code' => $log->source_device_code,
            'hours_worked' => $log->hours_worked,
            'overtime_hours' => $log->overtime_hours,
            'status' => $log->status,
            'late_minutes' => $log->late_minutes,
        ];
    }

    private function serializeToday(Employee $employee, ?AttendanceLog $log, ?string $timezone = null): array
    {
        $timezone ??= app('current_company')->timezone;

        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'checked_in' => (bool) $log?->check_in,
            'check_in_time' => $log?->check_in?->setTimezone($timezone)->format('H:i'),
            'check_out_time' => $log?->check_out?->setTimezone($timezone)->format('H:i'),
            'hours_worked' => $log?->hours_worked ?? '0.00',
            'status' => $log?->status ?? 'absent',
        ];
    }
}
