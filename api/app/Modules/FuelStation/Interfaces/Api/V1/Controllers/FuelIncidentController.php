<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelMaintenanceService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMaintenanceTaskRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\TransitionFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelMaintenanceTaskRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Incidents & maintenance FuelStation (FUEL-010, #5804).
 *
 * Manager + solution active (fail-closed) + tenant-scoped (404
 * cross-tenant AVANT tout traitement). Workflow audité, pièces jointes
 * contrôlées, rejeu d'incident idempotent.
 */
class FuelIncidentController extends Controller
{
    public function __construct(
        private readonly FuelMaintenanceService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelIncident::class);

        $query = FuelIncident::query()->withCount('attachments')->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        $incidents = $query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($incidents->items())->map(fn (FuelIncident $incident): array => $this->incidentPayload($incident)),
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

        $station = $this->stationInTenant($request->integer('station_id'), $actor);
        $result = $this->service->createIncident($actor, $station, $request->validated());

        return response()->json([
            'data' => $this->incidentPayload($result['incident']),
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? 200 : 201);
    }

    public function show(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $incident);

        $incident->loadCount('attachments')->load(['attachments', 'tasks']);

        return response()->json(['data' => $this->incidentPayload($incident)]);
    }

    public function transition(TransitionFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('transition', $incident);

        try {
            $incident = $this->service->transition($actor, $incident, $request->validated());
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'FUEL_INCIDENT_TRANSITION_INVALID',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $this->incidentPayload($incident)]);
    }

    public function attach(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($incident->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('attach', $incident);

        $request->validate([
            'attachment' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('attachment');

        $info = $this->service->attachFile($actor, $incident, $file);

        /** @var FuelIncidentAttachment $attachment */
        $attachment = FuelIncidentAttachment::query()->create([
            'company_id' => $incident->company_id,
            'incident_id' => $incident->id,
            'path' => $info['path'],
            'original_name' => $info['original_name'],
            'mime_type' => $info['mime_type'],
            'size_bytes' => $info['size_bytes'],
            'uploaded_by' => $actor->id,
        ]);

        return response()->json([
            'data' => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'created_at' => $attachment->created_at?->toISOString(),
            ],
        ], 201);
    }

    public function tasks(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelMaintenanceTask::class);

        $query = FuelMaintenanceTask::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('incident_id')) {
            $query->where('incident_id', $request->integer('incident_id'));
        }

        if ($request->filled('due_before')) {
            $query->where('due_at', '<=', $request->input('due_before'));
        }

        $tasks = $query->orderBy('due_at')->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($tasks->items())->map(fn (FuelMaintenanceTask $task): array => $this->taskPayload($task)),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function storeTask(StoreFuelMaintenanceTaskRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelMaintenanceTask::class);

        $station = $this->stationInTenant($request->integer('station_id'), $actor);
        $task = $this->service->createTask($actor, $station, $request->validated());

        return response()->json(['data' => $this->taskPayload($task)], 201);
    }

    public function updateTask(UpdateFuelMaintenanceTaskRequest $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('manageTask', $task);

        try {
            $task = $this->service->updateTask($actor, $task, $request->validated());
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'FUEL_TASK_STATUS_INVALID',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $this->taskPayload($task)]);
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

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /** @return array<string, mixed> */
    private function incidentPayload(FuelIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'station_id' => $incident->station_id,
            'equipment_type' => $incident->equipment_type,
            'equipment_id' => $incident->equipment_id,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'title' => $incident->title,
            'description' => $incident->description,
            'assigned_to' => $incident->assigned_to,
            'resolution_notes' => $incident->resolution_notes,
            'resolved_at' => $incident->resolved_at?->toISOString(),
            'attachments_count' => $incident->attachments_count ?? 0,
            'created_at' => $incident->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function taskPayload(FuelMaintenanceTask $task): array
    {
        return [
            'id' => $task->id,
            'station_id' => $task->station_id,
            'incident_id' => $task->incident_id,
            'task_type' => $task->task_type,
            'priority' => $task->priority,
            'status' => $task->status,
            'title' => $task->title,
            'description' => $task->description,
            'due_at' => $task->due_at?->toISOString(),
            'assigned_to' => $task->assigned_to,
            'completed_at' => $task->completed_at?->toISOString(),
            'notes' => $task->notes,
        ];
    }
}
