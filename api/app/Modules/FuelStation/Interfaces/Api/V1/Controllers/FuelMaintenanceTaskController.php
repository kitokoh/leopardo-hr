<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Enums\FuelMaintenanceTaskStatus;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\CompleteFuelMaintenanceTaskRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMaintenanceTaskRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelMaintenanceTaskRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5804/#5805 — Tâches de maintenance FuelStation (FUEL-010/FUEL-011).
 *
 * CRUD manager + achèvement (par manager ou assigné) — workflow audité.
 */
class FuelMaintenanceTaskController extends Controller
{
    use FuelIndexQueryTrait;

    public function __construct(private readonly FuelIncidentService $incidents)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelMaintenanceTask::class);

        $query = FuelMaintenanceTask::query()
            ->with('assignee:id,first_name,last_name')
            ->where('company_id', $actor->company_id);

        if (! $actor->isManager()) {
            $query->where('assigned_to', $actor->id);
        }

        $tasks = $this->applyFuelIndexQuery(
            $query,
            $request,
            ['title', 'priority', 'status', 'due_date', 'created_at'],
            ['status', 'type', 'priority', 'station_id', 'assigned_to'],
        );

        return response()->json(['data' => $tasks->through(fn (FuelMaintenanceTask $t): array => $this->payload($t))]);
    }

    public function store(StoreFuelMaintenanceTaskRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelMaintenanceTask::class);

        /** @var FuelMaintenanceTask $task */
        $task = FuelMaintenanceTask::query()->create(
            $request->validated() + [
                'company_id' => $actor->company_id,
                'status' => FuelMaintenanceTaskStatus::Open->value,
            ],
        );

        return response()->json(['data' => $this->payload($task)], 201);
    }

    public function show(Request $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($task, $request->user());
        $this->authorize('view', $task);

        return response()->json(['data' => $this->payload($task->loadMissing('assignee:id,first_name,last_name'))]);
    }

    public function update(UpdateFuelMaintenanceTaskRequest $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($task, $request->user());
        $this->authorize('update', $task);

        $task->update($request->validated());

        return response()->json(['data' => $this->payload($task->fresh())]);
    }

    public function complete(CompleteFuelMaintenanceTaskRequest $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($task, $request->user());
        $this->authorize('complete', $task);

        /** @var Employee $actor */
        $actor = $request->user();

        $updated = $this->incidents->completeTask($task, $actor, $request->validated()['completion_note'] ?? null);

        return response()->json(['data' => $this->payload($updated)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelMaintenanceTask $task): array
    {
        return [
            'id' => $task->id,
            'station_id' => $task->station_id,
            'equipment_type' => $task->equipment_type,
            'equipment_id' => $task->equipment_id,
            'type' => $task->type,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'assigned_to' => $task->assigned_to,
            'assignee' => $task->relationLoaded('assignee') && $task->assignee !== null
                ? ['id' => $task->assignee->id, 'first_name' => $task->assignee->first_name, 'last_name' => $task->assignee->last_name]
                : null,
            'due_date' => optional($task->due_date)->toDateString(),
            'completed_by' => $task->completed_by,
            'completed_at' => optional($task->completed_at)->toIso8601String(),
            'completion_note' => $task->completion_note,
            'created_at' => optional($task->created_at)->toIso8601String(),
        ];
    }

    private function guardTenant(FuelMaintenanceTask $task, mixed $actor): void
    {
        if ($actor instanceof Employee && (string) $task->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
