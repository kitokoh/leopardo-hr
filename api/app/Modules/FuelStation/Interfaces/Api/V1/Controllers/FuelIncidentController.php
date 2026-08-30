<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMaintenanceTaskRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Incidents, maintenance et tâches FuelStation (FUEL-010, issue #5804).
 *
 * - POST /fuel-station/incidents : signalement (tout employé du tenant).
 * - Workflow : assign / resolve / close (manager).
 * - GET /fuel-station/incidents[/{incident}] : liste/détail (manager ;
 *   auteur autorisé sur son propre incident).
 * - Tâches : CRUD manager + transition (open → in_progress → done) par le
 *   manager ou l'assigné.
 *
 * Isolation tenant fail-closed (404 cross-tenant) ; kill switch 403 si
 * solution inactive.
 */
class FuelIncidentController extends Controller
{
    public function __construct(private readonly FuelIncidentService $incidents) {}

    public function store(StoreFuelIncidentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('report', FuelIncident::class);

        $incident = $this->incidents->report(
            FuelIncident::query()->make(),
            $actor,
            $request->validated(),
        );

        return response()->json(['data' => $this->incidentPayload($incident)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelIncident::class);

        $query = FuelIncident::query()
            ->with('reporter:id,first_name,last_name', 'assignee:id,first_name,last_name')
            ->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        $incidents = $query->orderByDesc('reported_at')->get();

        return response()->json([
            'data' => $incidents->map(fn (FuelIncident $incident): array => $this->incidentPayload($incident)),
        ]);
    }

    public function show(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($incident, $actor);
        $this->authorize('view', $incident);

        $incident->load('reporter:id,first_name,last_name', 'assignee:id,first_name,last_name', 'tasks');

        return response()->json(['data' => $this->incidentPayload($incident)]);
    }

    public function assign(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($incident, $actor);
        $this->authorize('assign', $incident);

        /** @var array{assigned_to?: int|null} $data */
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $incident = $this->incidents->assign($incident, $actor, $data);

        return response()->json(['data' => $this->incidentPayload($incident)]);
    }

    public function resolve(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($incident, $actor);
        $this->authorize('resolve', $incident);

        $incident = $this->incidents->resolve($incident, $actor);

        return response()->json(['data' => $this->incidentPayload($incident)]);
    }

    public function close(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($incident, $actor);
        $this->authorize('close', $incident);

        $incident = $this->incidents->close($incident, $actor);

        return response()->json(['data' => $this->incidentPayload($incident)]);
    }

    public function storeTask(StoreFuelMaintenanceTaskRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('createTask', FuelMaintenanceTask::class);

        $task = $this->incidents->createTask(
            FuelMaintenanceTask::query()->make(),
            $actor,
            $request->validated(),
        );

        return response()->json(['data' => $this->taskPayload($task)], 201);
    }

    public function tasks(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAnyTask', FuelMaintenanceTask::class);

        $query = FuelMaintenanceTask::query()
            ->with('assignee:id,first_name,last_name')
            ->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        $tasks = $query->orderByDesc('due_at')->get();

        return response()->json([
            'data' => $tasks->map(fn (FuelMaintenanceTask $task): array => $this->taskPayload($task)),
        ]);
    }

    public function transitionTask(Request $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($task, $actor);
        $this->authorize('transitionTask', $task);

        /** @var array{status: string, assigned_to?: int|null} $data */
        $data = $request->validate([
            'status' => ['required', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $task = $this->incidents->transitionTask(
            $task,
            (string) $data['status'],
            $actor,
            $data,
        );

        return response()->json(['data' => $this->taskPayload($task)]);
    }

    /** @return array<string, mixed> */
    private function incidentPayload(FuelIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'station_id' => $incident->station_id,
            'category' => $incident->category,
            'severity' => $incident->severity,
            'description_redacted' => $incident->description_redacted,
            'status' => $incident->status,
            'reported_by' => $incident->reported_by,
            'reported_at' => $incident->reported_at->toIso8601String(),
            'assigned_to' => $incident->assigned_to,
            'assigned_at' => $incident->assigned_at?->toIso8601String(),
            'resolved_by' => $incident->resolved_by,
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'closed_by' => $incident->closed_by,
            'closed_at' => $incident->closed_at?->toIso8601String(),
            'attachments_metadata' => $incident->attachments_metadata,
            'external_id' => $incident->external_id,
            'tasks_count' => $incident->relationLoaded('tasks') ? $incident->tasks->count() : null,
            'created_at' => $incident->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function taskPayload(FuelMaintenanceTask $task): array
    {
        return [
            'id' => $task->id,
            'station_id' => $task->station_id,
            'incident_id' => $task->incident_id,
            'title' => $task->title,
            'description_redacted' => $task->description_redacted,
            'task_type' => $task->task_type,
            'priority' => $task->priority,
            'status' => $task->status,
            'assigned_to' => $task->assigned_to,
            'due_at' => $task->due_at?->toIso8601String(),
            'started_at' => $task->started_at?->toIso8601String(),
            'completed_by' => $task->completed_by,
            'completed_at' => $task->completed_at?->toIso8601String(),
            'created_by' => $task->created_by,
            'external_id' => $task->external_id,
            'created_at' => $task->created_at?->toIso8601String(),
        ];
    }

    private function assertTenantOwned(Model $model, Employee $actor): void
    {
        if ($model->getAttribute('company_id') !== $actor->company_id) {
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
