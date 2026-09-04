<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelIncidentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMaintenanceTaskRequest
use Illuminate\Database\Eloquent\Model;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment
use App\Modules\FuelStation\Domain\Models\FuelStation
use App\Modules\FuelStation\Infrastructure\Services\FuelMaintenanceService
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMaintenanceTaskRequest
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\TransitionFuelIncidentRequest
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelMaintenanceTaskRequest

/**
 * Incidents équipements (FUEL-010, issue #5804).
 *
 * deny-by-default (FuelIncidentPolicy) : signalement par tout employé du
 * tenant ; assignation/résolution/clôture par le manager. Workflow audité,
 * pièces jointes contrôlées, notification sans PII (FUEL-019).
 */
class FuelIncidentController extends Controller
{
    public function __construct(private readonly FuelIncidentService $incidents) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelIncident::class);

        $query = FuelIncident::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        $incidents = $query->orderByDesc('occurred_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($incidents->items())->map(fn (FuelIncident $i): array => $this->payload($i)),
            'meta' => [
                'current_page' => $incidents->currentPage(),
                'last_page' => $incidents->lastPage(),
                'total' => $incidents->total(),
            ],
        ]);
    }

    public function store(StoreFuelIncidentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelIncident::class);

        $incident = $this->incidents->report($actor, $request->validated());

        // Pièces jointes contrôlées (métadonnées uniquement).
        foreach ((array) $request->input('attachments', []) as $attachment) {
            if (is_array($attachment)) {
                $this->incidents->attach(
                    $actor,
                    $incident,
                    (string) ($attachment['file_name'] ?? ''),
                    (string) ($attachment['mime_type'] ?? ''),
                    (int) ($attachment['size_bytes'] ?? 0)
                );
            }
        }

        return response()->json(['data' => $this->payload($incident->refresh())], 201);
    }

    public function show(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $incident);

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function assign(UpdateFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('assign', $incident);

        $assigneeId = (int) $request->input('assigned_to', 0);

        abort_if($assigneeId <= 0, 422, 'ASSIGNEE_REQUIRED');

        $incident = $this->incidents->assign($actor, $incident, $assigneeId);

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function resolve(UpdateFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('resolve', $incident);

        $notes = (string) $request->input('resolution_notes', '');

        abort_if(trim($notes) === '', 422, 'RESOLUTION_NOTES_REQUIRED');

        $incident = $this->incidents->resolve($actor, $incident, $notes);

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function close(UpdateFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('close', $incident);

        $notes = (string) $request->input('closure_notes', '');

        abort_if(trim($notes) === '', 422, 'CLOSURE_NOTES_REQUIRED');

        $incident = $this->incidents->close($actor, $incident, $notes);

        return response()->json(['data' => $this->payload($incident)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'company_id' => $incident->company_id,
            'station_id' => $incident->station_id,
            'equipment_type' => $incident->equipment_type,
            'equipment_id' => $incident->equipment_id,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'title' => $incident->title,
            'description' => $incident->description,
            'reported_by' => $incident->reported_by,
            'assigned_to' => $incident->assigned_to,
            'occurred_at' => $incident->occurred_at->toISOString(),
            'resolved_at' => $incident->resolved_at?->toISOString(),
            'resolved_by' => $incident->resolved_by,
            'resolution_notes' => $incident->resolution_notes,
            'closed_at' => $incident->closed_at?->toISOString(),
            'closed_by' => $incident->closed_by,
            'closure_notes' => $incident->closure_notes,
            'created_at' => $incident->created_at?->toISOString(),
            'updated_at' => $incident->updated_at?->toISOString(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
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


    private function stationInTenant(int $stationId, Employee $actor): FuelStation
    {
        /** @var FuelStation|null $station */
        $station = FuelStation::query()
            ->where('company_id', $actor->company_id)
            ->find($stationId);

        if (! $station instanceof FuelStation) {
            abort(404);
        }

        return $station;
    }
}