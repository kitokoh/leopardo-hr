<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CrmTaskResource;
use App\Modules\CRM\Domain\Models\CrmTask;
use App\Modules\CRM\Interfaces\Api\V1\Requests\IndexCrmTaskRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmTaskRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmTaskRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD des tâches CRM client — Issue #5711 (CRM-V0-07).
 *
 * Tâches bornées : statuts/priorités allowlistés, `completed_at` dérivé
 * côté serveur (passage à `done`, idempotent). RBAC : middleware
 * `api.manager:principal,rh` + Policy `CrmTaskPolicy` (assigné en
 * lecture/édition). Isolation tenant : scope `BelongsToCompany` (404).
 */
class CrmTaskController extends Controller
{
    public function index(IndexCrmTaskRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmTask::class);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = CrmTask::query();

        foreach (['status', 'priority', 'assignee_id'] as $filter) {
            if (isset($validated[$filter]) && $validated[$filter] !== null) {
                $query->where($filter, $validated[$filter]);
            }
        }

        if (! empty($validated['related_type'])) {
            $query->where('related_type', (string) $validated['related_type']);
        }

        if (! empty($validated['related_id'])) {
            $query->where('related_id', (int) $validated['related_id']);
        }

        if (! empty($validated['due_from'])) {
            $query->whereDate('due_at', '>=', (string) $validated['due_from']);
        }

        if (! empty($validated['due_to'])) {
            $query->whereDate('due_at', '<=', (string) $validated['due_to']);
        }

        $sortBy = (string) ($validated['sort_by'] ?? 'due_at');
        $sortDir = (string) ($validated['sort_dir'] ?? 'asc');
        $query->orderBy($sortBy, $sortDir)->orderBy('id', $sortDir);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => CrmTaskResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreCrmTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', CrmTask::class);

        $payload = $request->validated();
        $payload['created_by_id'] = $request->user()?->id;

        $task = CrmTask::query()->create($this->withCompletionState($payload));

        return response()->json([
            'data' => new CrmTaskResource($task),
        ], 201);
    }

    public function show(Request $request, CrmTask $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json([
            'data' => new CrmTaskResource($task),
        ]);
    }

    public function update(UpdateCrmTaskRequest $request, CrmTask $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $task->update($this->withCompletionState($request->validated()));

        return response()->json([
            'data' => new CrmTaskResource($task->refresh()),
        ]);
    }

    public function destroy(Request $request, CrmTask $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->json(null, 204);
    }

    /**
     * Dérive `completed_at` (idempotent) lors des transitions de statut.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withCompletionState(array $payload): array
    {
        $status = $payload['status'] ?? null;

        if ($status === 'done') {
            $payload['completed_at'] ??= now();
        } elseif ($status !== null && $status !== 'done') {
            // Retour en arrière : la tâche n'est plus terminée.
            $payload['completed_at'] = null;
        }

        return $payload;
    }
}
