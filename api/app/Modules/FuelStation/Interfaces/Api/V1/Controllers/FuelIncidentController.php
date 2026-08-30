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
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\AssignFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\ResolveFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelIncidentAttachmentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelIncidentRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMaintenanceTaskRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelMaintenanceTaskRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Incidents, maintenance et tâches FuelStation (FUEL-010, issue #5804).
 *
 * Workflow audité (événements tracés dans audit_logs), assignation
 * tenant-only, résolution avec notes obligatoires, pièces jointes
 * contrôlées. Gateé par le flag `fuel_station` (403 fail-closed).
 */
class FuelIncidentController extends Controller
{
    public function __construct(private readonly FuelIncidentService $incidents) {}

    public function store(StoreFuelIncidentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelIncident::class);

        $incident = $this->incidents->report($actor, $request->validated());

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelIncident::class);

        $query = FuelIncident::query()->with('attachments:id,incident_id,filename,size_bytes,mime_type');

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->integer('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $incidents = $query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($incidents->items())->map(fn (FuelIncident $incident): array => $this->payload($incident)),
            'meta' => ['current_page' => $incidents->currentPage(), 'last_page' => $incidents->lastPage(), 'total' => $incidents->total()],
        ]);
    }

    public function show(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($incident->company_id !== $actor->company_id, 404);

        $this->authorize('view', $incident);

        $incident->load('attachments:id,incident_id,filename,size_bytes,mime_type');

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function assign(AssignFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($incident->company_id !== $actor->company_id, 404);

        $this->authorize('assign', $incident);

        $incident = $this->incidents->assign($incident, $actor, $request->validated());

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function start(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($incident->company_id !== $actor->company_id, 404);

        $this->authorize('update', $incident);

        $incident = $this->incidents->start($incident);

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function resolve(ResolveFuelIncidentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($incident->company_id !== $actor->company_id, 404);

        $this->authorize('resolve', $incident);

        $incident = $this->incidents->resolve($incident, $actor, $request->validated());

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function close(Request $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($incident->company_id !== $actor->company_id, 404);

        $this->authorize('close', $incident);

        $incident = $this->incidents->close($incident);

        return response()->json(['data' => $this->payload($incident)]);
    }

    public function addAttachment(StoreFuelIncidentAttachmentRequest $request, FuelIncident $incident): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($incident->company_id !== $actor->company_id, 404);

        $this->authorize('update', $incident);

        $attachment = $this->incidents->addAttachment($incident, $actor, $request->validated());

        return response()->json(['data' => $this->attachmentPayload($attachment)], 201);
    }

    public function deleteAttachment(Request $request, FuelIncidentAttachment $attachment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($attachment->company_id !== $actor->company_id, 404);

        $this->authorize('manageAttachment', $attachment);

        $this->incidents->deleteAttachment($attachment);

        return response()->json(['data' => ['deleted' => true]]);
    }

    // — Maintenance tasks —

    public function storeTask(StoreFuelMaintenanceTaskRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('manageTask', FuelMaintenanceTask::class);

        $task = $this->incidents->createTask($actor, $request->validated());

        return response()->json(['data' => $this->taskPayload($task)], 201);
    }

    public function indexTasks(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('manageTask', FuelMaintenanceTask::class);

        $query = FuelMaintenanceTask::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('incident_id')) {
            $query->where('incident_id', $request->integer('incident_id'));
        }

        $tasks = $query->orderByDesc('scheduled_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($tasks->items())->map(fn (FuelMaintenanceTask $task): array => $this->taskPayload($task)),
            'meta' => ['current_page' => $tasks->currentPage(), 'last_page' => $tasks->lastPage(), 'total' => $tasks->total()],
        ]);
    }

    public function updateTask(UpdateFuelMaintenanceTaskRequest $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        abort_if($task->company_id !== $actor->company_id, 404);

        $this->authorize('manageTask', $task);

        $task = $this->incidents->updateTask($task, $request->validated());

        return response()->json(['data' => $this->taskPayload($task)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'station_id' => $incident->station_id,
            'equipment_type' => $incident->equipment_type,
            'equipment_id' => $incident->equipment_id,
            'title' => $incident->title,
            'description' => $incident->description,
            'priority' => $incident->priority,
            'status' => $incident->status,
            'reported_by' => $incident->reported_by,
            'assigned_to' => $incident->assigned_to,
            'resolution_notes' => $incident->resolution_notes,
            'resolved_at' => $incident->resolved_at?->toISOString(),
            'closed_at' => $incident->closed_at?->toISOString(),
            'attachments' => $incident->relationLoaded('attachments')
                ? $incident->attachments->map(fn (FuelIncidentAttachment $a): array => $this->attachmentPayload($a))->values()
                : [],
            'created_at' => $incident->created_at?->toISOString(),
            'updated_at' => $incident->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentPayload(FuelIncidentAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'incident_id' => $attachment->incident_id,
            'filename' => $attachment->filename,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'uploaded_by' => $attachment->uploaded_by,
            'created_at' => $attachment->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(FuelMaintenanceTask $task): array
    {
        return [
            'id' => $task->id,
            'incident_id' => $task->incident_id,
            'title' => $task->title,
            'task_type' => $task->task_type,
            'scheduled_at' => $task->scheduled_at?->toISOString(),
            'status' => $task->status,
            'assigned_to' => $task->assigned_to,
            'completed_at' => $task->completed_at?->toISOString(),
            'notes' => $task->notes,
            'created_by' => $task->created_by,
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
