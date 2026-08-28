<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Models\CrmTask;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmTaskRequest;
use App\Modules\CRM\Interfaces\Api\V1\Requests\UpdateCrmTaskRequest;
use App\Modules\CRM\Interfaces\Api\V1\Support\CrmQueryHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #5711 — Tâches CRM client (tenant).
 *
 * Un assigné non-manager peut faire évoluer le statut/la priorité de sa
 * tâche (Policy `update`) ; la complétion est horodatée automatiquement.
 */
class CrmTaskController extends Controller
{
    use CrmQueryHelpers;
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrmTask::class);
        $this->rejectUnknownQueryKeys($request, [
            'per_page', 'sort_by', 'sort_dir',
            'status', 'priority', 'assigned_to', 'lead_id', 'opportunity_id', 'account_id',
        ]);

        $query = CrmTask::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', (int) $request->input('assigned_to'));
        }
        foreach (['account_id', 'contact_id', 'lead_id', 'opportunity_id'] as $relationColumn) {
            if ($request->filled($relationColumn)) {
                $query->where($relationColumn, (int) $request->input($relationColumn));
            }
        }

        $query = $this->applySort($query, $request, [
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'due_at' => 'due_at',
            'priority' => 'priority',
        ]);

        return new JsonResponse($query->paginate($this->perPage($request)));
    }

    public function store(StoreCrmTaskRequest $request): JsonResponse
    {
        $this->authorize('create', CrmTask::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $task = CrmTask::query()->create([
            'company_id' => $actor->company_id,
            'account_id' => $request->validated('account_id'),
            'contact_id' => $request->validated('contact_id'),
            'lead_id' => $request->validated('lead_id'),
            'opportunity_id' => $request->validated('opportunity_id'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status', CrmTask::STATUS_TODO),
            'priority' => $request->validated('priority', CrmTask::PRIORITY_MEDIUM),
            'due_at' => $request->validated('due_at'),
            'assigned_to' => $request->validated('assigned_to'),
            'created_by' => $actor->id,
        ]);

        return new JsonResponse(['data' => $task], 201);
    }

    public function show(CrmTask $crmTask): JsonResponse
    {
        $this->authorize('view', $crmTask);

        return new JsonResponse(['data' => $crmTask]);
    }

    public function update(UpdateCrmTaskRequest $request, CrmTask $crmTask): JsonResponse
    {
        $this->authorize('update', $crmTask);

        $data = $request->validated();

        // Complétion horodatée (et dé-complétion si retour arrière).
        if (array_key_exists('status', $data)) {
            if ($data['status'] === CrmTask::STATUS_DONE && $crmTask->completed_at === null) {
                $data['completed_at'] = now();
                $data['completed_by'] = $request->user()?->id;
            } elseif ($data['status'] !== CrmTask::STATUS_DONE) {
                $data['completed_at'] = null;
                $data['completed_by'] = null;
            }
        }

        $crmTask->update($data);

        return new JsonResponse(['data' => $crmTask->fresh()]);
    }

    public function destroy(CrmTask $crmTask): JsonResponse
    {
        $this->authorize('delete', $crmTask);

        $crmTask->delete();

        return new JsonResponse(null, 204);
    }
}
