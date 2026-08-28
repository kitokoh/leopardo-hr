<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Actions\CompleteCrmTaskAction;
use App\Modules\CRM\Application\Actions\CreateCrmTaskAction;
use App\Modules\CRM\Application\Actions\ReopenCrmTaskAction;
use App\Modules\CRM\Application\Actions\UpdateCrmTaskAction;
use App\Modules\CRM\Application\DTOs\CreateCrmTaskDTO;
use App\Modules\CRM\Application\DTOs\UpdateCrmTaskDTO;
use App\Modules\CRM\Application\Queries\ListCrmTasksQuery;
use App\Modules\CRM\Domain\Models\CrmTask;
use App\Modules\CRM\Interfaces\Api\V1\Requests\CrmListTasksRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\CrmStoreTaskRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\CrmUpdateTaskRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmTaskResource;
use Illuminate\Http\JsonResponse;

/**
 * Issue #5720 — CRUD tâches CRM + transitions (complete/reopen).
 *
 * RBAC : middleware `api.manager:principal,rh,marketing` + Policy
 * `CrmTaskPolicy` (l'assigné peut voir/faire évoluer sa tâche). Isolation
 * tenant : scope BelongsToCompany (un task d'un autre tenant → 404).
 */
class CrmTaskController extends Controller
{
    public function __construct(
        private readonly ListCrmTasksQuery $listCrmTasksQuery,
        private readonly CreateCrmTaskAction $createCrmTaskAction,
        private readonly UpdateCrmTaskAction $updateCrmTaskAction,
        private readonly CompleteCrmTaskAction $completeCrmTaskAction,
        private readonly ReopenCrmTaskAction $reopenCrmTaskAction,
    ) {}

    public function index(CrmListTasksRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CrmTask::class);

        $input = $request->validated();

        return CrmTaskResource::collection(
            $this->listCrmTasksQuery->execute($input)
        )->response();
    }

    public function store(CrmStoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', CrmTask::class);

        $validated = $request->validated();

        $task = $this->createCrmTaskAction->execute(new CreateCrmTaskDTO(
            title: $validated['title'],
            description: $validated['description'] ?? null,
            dueAt: isset($validated['due_at']) ? \Illuminate\Support\Carbon::parse($validated['due_at']) : null,
            priority: $validated['priority'] ?? 'medium',
            assigneeId: isset($validated['assignee_id']) ? (int) $validated['assignee_id'] : null,
            accountId: isset($validated['account_id']) ? (int) $validated['account_id'] : null,
            contactId: isset($validated['contact_id']) ? (int) $validated['contact_id'] : null,
        ));

        return (new CrmTaskResource($task))->response()->setStatusCode(201);
    }

    public function show(CrmTask $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load('assignee:id,first_name,last_name');

        return (new CrmTaskResource($task))->response();
    }

    public function update(CrmUpdateTaskRequest $request, CrmTask $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validated();

        $task = $this->updateCrmTaskAction->execute($task, new UpdateCrmTaskDTO(
            title: $validated['title'] ?? null,
            description: array_key_exists('description', $validated) ? $validated['description'] : null,
            dueAt: isset($validated['due_at']) ? \Illuminate\Support\Carbon::parse($validated['due_at']) : null,
            status: $validated['status'] ?? null,
            priority: $validated['priority'] ?? null,
            assigneeId: isset($validated['assignee_id']) ? (int) $validated['assignee_id'] : null,
            accountId: isset($validated['account_id']) ? (int) $validated['account_id'] : null,
            contactId: isset($validated['contact_id']) ? (int) $validated['contact_id'] : null,
        ));

        return (new CrmTaskResource($task->load('assignee:id,first_name,last_name')))->response();
    }

    public function complete(CrmTask $task): JsonResponse
    {
        $this->authorize('complete', $task);

        return (new CrmTaskResource($this->completeCrmTaskAction->execute($task)))->response();
    }

    public function reopen(CrmTask $task): JsonResponse
    {
        $this->authorize('reopen', $task);

        return (new CrmTaskResource($this->reopenCrmTaskAction->execute($task)))->response();
    }

    public function destroy(CrmTask $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['data' => null], 204);
    }
}
