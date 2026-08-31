<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\ShiftOverlapException;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;
use App\Modules\FuelStation\Infrastructure\Services\FuelShiftService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelShiftAssignmentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelShiftRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelShiftRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API des shifts FuelStation (FUEL-005, issue #5799).
 *
 * Routes manager : CRUD /fuel/shifts + affectations. Route self-service :
 * GET /fuel/me/shifts (affectations du pompiste connecté, fenêtre de dates).
 * Isolation tenant fail-closed (404 cross-tenant).
 */
class FuelShiftController extends Controller
{
    public function __construct(private readonly FuelShiftService $shifts) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelShift::class);

        $query = FuelShift::query()
            ->withCount('assignments')
            ->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $shifts = $query->orderBy('start_time')->get();

        return response()->json([
            'data' => $shifts->map(fn (FuelShift $shift): array => $this->shiftPayload($shift)),
        ]);
    }

    public function store(StoreFuelShiftRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelShift::class);

        $shift = $this->shifts->create($actor, $request->validated());

        return response()->json(['data' => $this->shiftPayload($shift)], 201);
    }

    public function show(Request $request, FuelShift $shift): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($shift->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $shift);

        return response()->json(['data' => $this->shiftPayload($shift)]);
    }

    public function update(UpdateFuelShiftRequest $request, FuelShift $shift): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($shift->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $shift);

        $shift = $this->shifts->update($shift, $request->validated());

        return response()->json(['data' => $this->shiftPayload($shift)]);
    }

    public function destroy(Request $request, FuelShift $shift): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($shift->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $shift);

        $this->shifts->delete($shift);

        return response()->json(['data' => $this->shiftPayload($shift)]);
    }

    public function assignments(Request $request, FuelShift $shift): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($shift->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('viewAssignments', $shift);

        $from = $this->dateFilter($request->input('date_from'));
        $to = $this->dateFilter($request->input('date_to'));

        $assignments = $this->shifts->assignmentsForShift($shift, $from, $to);

        return response()->json([
            'data' => $assignments->map(fn (FuelShiftAssignment $assignment): array => $this->assignmentPayload($assignment)),
        ]);
    }

    public function assign(StoreFuelShiftAssignmentRequest $request, FuelShift $shift): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($shift->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('assign', $shift);

        try {
            $assignment = $this->shifts->assign($shift, $actor, $request->validated());
        } catch (ShiftOverlapException) {
            abort(422, 'SHIFT_OVERLAP');
        }

        return response()->json(['data' => $this->assignmentPayload($assignment->load('employee:id,first_name,last_name'))], 201);
    }

    public function cancelAssignment(Request $request, FuelShiftAssignment $assignment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($assignment->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('cancelAssignment', $assignment);

        $assignment = $this->shifts->cancelAssignment($assignment, $actor);

        return response()->json(['data' => $this->assignmentPayload($assignment->load('employee:id,first_name,last_name'))]);
    }

    /**
     * Self-service pompiste : ses propres affectations sur une fenêtre.
     */
    public function myShifts(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $from = $this->dateFilter($request->input('date_from'));
        $to = $this->dateFilter($request->input('date_to'));

        $assignments = $this->shifts->assignmentsForEmployee($actor, $from, $to);

        return response()->json([
            'data' => $assignments->map(fn (FuelShiftAssignment $assignment): array => $this->assignmentPayload($assignment)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftPayload(FuelShift $shift): array
    {
        return [
            'id' => $shift->id,
            'company_id' => $shift->company_id,
            'station_id' => $shift->station_id,
            'name' => $shift->name,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'status' => $shift->status,
            'notes' => $shift->notes,
            'assignments_count' => $shift->assignments_count ?? null,
            'created_at' => $shift->created_at?->toISOString(),
            'updated_at' => $shift->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(FuelShiftAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'company_id' => $assignment->company_id,
            'shift_id' => $assignment->shift_id,
            'shift' => $assignment->relationLoaded('shift')
                ? [
                    'id' => $assignment->shift?->id,
                    'name' => $assignment->shift?->name,
                    'start_time' => $assignment->shift?->start_time,
                    'end_time' => $assignment->shift?->end_time,
                ]
                : null,
            'employee_id' => $assignment->employee_id,
            'employee' => $assignment->relationLoaded('employee')
                ? [
                    'id' => $assignment->employee?->id,
                    'first_name' => $assignment->employee?->first_name,
                    'last_name' => $assignment->employee?->last_name,
                ]
                : null,
            'assignment_date' => $assignment->assignment_date->toDateString(),
            'status' => $assignment->status,
            'notes' => $assignment->notes,
            'created_at' => $assignment->created_at?->toISOString(),
            'updated_at' => $assignment->updated_at?->toISOString(),
        ];
    }

    private function dateFilter(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $value);

        if ($parsed === null) {
            return null;
        }

        return $parsed->startOfDay();
    }



    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
