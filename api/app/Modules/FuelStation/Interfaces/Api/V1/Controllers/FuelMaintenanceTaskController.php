<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelMaintenanceTaskRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API des tâches de maintenance FuelStation (FUEL-010, #5804).
 *
 * CRUD manager + transition `complete` (completed_by/completed_at tracés),
 * tenant-scoped, 404 sûr cross-tenant.
 */
class FuelMaintenanceTaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelMaintenanceTask::class);

        $query = FuelMaintenanceTask::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', (int) $request->input('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $tasks = $query->orderByDesc('created_at')->paginate((int) min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $tasks->map(fn (FuelMaintenanceTask $task): array => $this->payload($task)),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function store(StoreFuelMaintenanceTaskRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelMaintenanceTask::class);

        /** @var FuelMaintenanceTask $task */
        $task = FuelMaintenanceTask::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'status' => FuelMaintenanceTask::STATUS_PENDING,
        ]));

        return response()->json(['data' => $this->payload($task)], 201);
    }

    public function show(Request $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $task);

        return response()->json(['data' => $this->payload($task)]);
    }

    public function update(StoreFuelMaintenanceTaskRequest $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $task);

        $task->update($request->validated());

        return response()->json(['data' => $this->payload($task->refresh())]);
    }

    public function complete(Request $request, FuelMaintenanceTask $task): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $task);

        if ($task->status === FuelMaintenanceTask::STATUS_COMPLETED) {
            return response()->json(['data' => $this->payload($task)]);
        }

        $task->update([
            'status' => FuelMaintenanceTask::STATUS_COMPLETED,
            'completed_by' => $actor->id,
            'completed_at' => Carbon::now(),
        ]);

        return response()->json(['data' => $this->payload($task->refresh())]);
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
            'type' => $task->type,
            'priority' => $task->priority,
            'status' => $task->status,
            'title' => $task->title,
            'description' => $task->description,
            'scheduled_for' => $task->scheduled_for?->toDateString(),
            'assigned_to' => $task->assigned_to,
            'completed_by' => $task->completed_by,
            'completed_at' => $task->completed_at?->toISOString(),
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }
}
