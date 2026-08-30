<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMaintenanceTaskRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelMaintenanceTaskRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tâches de maintenance (FUEL-010, issue #5804). deny-by-default
 * (FuelMaintenanceTaskPolicy) : gestion manager, lecture employé du tenant.
 */
class FuelMaintenanceTaskController extends Controller
{
    public function __construct(private readonly FuelIncidentService $incidents) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelMaintenanceTask::class);

        $query = FuelMaintenanceTask::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tasks = $query->orderByDesc('scheduled_for')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($tasks->items())->map(fn (FuelMaintenanceTask $t): array => $this->payload($t)),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function store(StoreFuelMaintenanceTaskRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelMaintenanceTask::class);

        $task = $this->incidents->createTask($actor, $request->validated());

        return response()->json(['data' => $this->payload($task->refresh())], 201);
    }

    public function show(Request $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($task->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $task);

        return response()->json(['data' => $this->payload($task)]);
    }

    public function update(UpdateFuelMaintenanceTaskRequest $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($task->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $task);

        $task = $this->incidents->updateTask($actor, $task, $request->validated());

        return response()->json(['data' => $this->payload($task)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelMaintenanceTask $task): array
    {
        return [
            'id' => $task->id,
            'company_id' => $task->company_id,
            'station_id' => $task->station_id,
            'incident_id' => $task->incident_id,
            'task_type' => $task->task_type,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'assigned_to' => $task->assigned_to,
            'scheduled_for' => $task->scheduled_for?->toDateString(),
            'completed_at' => $task->completed_at?->toDateString(),
            'completed_by' => $task->completed_by,
            'completion_notes' => $task->completion_notes,
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
