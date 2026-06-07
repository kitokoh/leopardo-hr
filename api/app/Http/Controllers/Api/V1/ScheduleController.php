<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Schedule\AssignScheduleEmployeesRequest;
use App\Http\Requests\Api\V1\Schedule\StoreScheduleRequest;
use App\Http\Requests\Api\V1\Schedule\UpdateScheduleRequest;
use App\Http\Resources\Api\V1\ScheduleResource;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\Cache\TenantCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly TenantCacheService $tenantCache,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $schedules = $this->tenantCache->rememberSchedules(
            $user->company_id,
            fn () => Schedule::query()
                ->select([
                    'id',
                    'company_id',
                    'name',
                    'start_time',
                    'end_time',
                    'break_minutes',
                    'break_rules',
                    'work_days',
                    'rest_days',
                    'leave_rules',
                    'assignment_notes',
                    'late_tolerance_minutes',
                    'overtime_threshold_daily',
                    'overtime_threshold_weekly',
                    'is_default',
                    'created_at',
                    'updated_at',
                ])
                ->orderBy('name')
                ->get()
        );

        return ScheduleResource::collection($schedules);
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! empty($request->validated('is_default'))) {
            Schedule::where('company_id', $actor->company_id)->where('is_default', true)->update(['is_default' => false]);
        }

        $schedule = Schedule::create([
            'company_id' => $actor->company_id,
            'work_days' => $request->validated('work_days') ?? [1, 2, 3, 4, 5],
            ...$request->validated(),
        ]);

        $this->tenantCache->invalidateSchedules($actor->company_id);
        $this->tenantCache->invalidateEmployees($actor->company_id);

        return (new ScheduleResource($schedule))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Schedule $schedule): ScheduleResource
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return new ScheduleResource($schedule);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): ScheduleResource
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! empty($request->validated('is_default'))) {
            Schedule::where('company_id', $actor->company_id)->where('id', '!=', $schedule->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $schedule->update($request->validated());

        $this->tenantCache->invalidateSchedules($actor->company_id);
        $this->tenantCache->invalidateEmployees($actor->company_id);

        return new ScheduleResource($schedule->fresh());
    }

    public function assignEmployees(AssignScheduleEmployeesRequest $request, Schedule $schedule): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ((string) $schedule->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        /** @var array<int, int> $employeeIds */
        $employeeIds = array_values(
            array_unique(array_map('intval', $request->validated('employee_ids')))
        );
        $employees = Employee::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('id', $employeeIds)
            ->get(['id']);

        if ($employees->count() !== count($employeeIds)) {
            return response()->json([
                'message' => 'Some employees cannot receive this schedule.',
                'errors' => [
                    'employee_ids' => ['Only employees from the current company can be assigned.'],
                ],
            ], 422);
        }

        Employee::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('id', $employeeIds)
            ->update(['schedule_id' => $schedule->id]);

        $this->tenantCache->invalidateEmployees($actor->company_id);

        return response()->json([
            'data' => [
                'schedule' => new ScheduleResource($schedule->fresh()),
                'assigned_count' => count($employeeIds),
                'employee_ids' => $employeeIds,
            ],
        ]);
    }

    public function destroy(Request $request, Schedule $schedule): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }
        if ($schedule->is_default) {
            abort(422, 'Cannot delete the default schedule.');
        }

        $schedule->delete();

        $this->tenantCache->invalidateSchedules($user->company_id);
        $this->tenantCache->invalidateEmployees($user->company_id);

        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}
