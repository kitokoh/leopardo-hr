<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TaskCommentResource;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use App\Http\Requests\Api\V1\Task\AddCommentTaskRequest;
use App\Http\Requests\Api\V1\Task\StoreTaskRequest;
use App\Http\Requests\Api\V1\Task\TaskIndexRequest;
use App\Http\Requests\Api\V1\Task\UpdateTaskRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;


class TaskController extends Controller
{
    public function index(TaskIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $request->validate(['project_id' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'in:todo,inprogress,review,done,rejected,cancelled'], 'priority' => ['nullable', 'in:low,normal,high,urgent'], 'assigned_to' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $query = Task::query()->where('company_id', $actor->company_id);

        if (! $actor->isManager()) {
            $query->where(fn ($q) => $q->whereJsonContains('assigned_to', $actor->id)->orWhere('created_by', $actor->id));
        } elseif ($request->filled('assigned_to')) {
            $query->whereJsonContains('assigned_to', $request->integer('assigned_to'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $perPage = $request->integer('per_page', 15);

        return TaskResource::collection($query->orderBy('due_date')->orderByDesc('created_at')->paginate($perPage))
            ->response();
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $data = $request->validated();

        if (! $actor->isManager()) {
            $assignedTo = $data['assigned_to'] ?? [$actor->id];
            if ($assignedTo !== [$actor->id]) {
                abort(403);
            }
            $data['assigned_to'] = [$actor->id];
        }

        $task = Task::create($this->filterWritableTaskColumns([
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'assigned_to' => $data['assigned_to'] ?? [],
            'status' => 'todo',
            'priority' => $data['priority'] ?? 'normal',
            'visibility' => $data['visibility'] ?? 'visible',
            ...$data,
        ]));

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && ! in_array($actor->id, $task->assigned_to ?? []) && $task->created_by !== $actor->id) {
            abort(403);
        }

        return (new TaskResource($task->load('comments.author')))->response();
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }

        $canUpdate = $actor->isManager() || $task->created_by === $actor->id || in_array($actor->id, $task->assigned_to ?? []);
        if (! $canUpdate) {
            abort(403);
        }

        $data = $request->validated();
        if (! $actor->isManager()) {
            $data = Arr::only($data, ['status', 'completed_minutes', 'completion_note']);
        }
        $data = $this->filterWritableTaskColumns($data);
        $this->applyCompletionMetrics($task, $data);
        $task->update($data);

        return (new TaskResource($task->fresh()))->response();
    }

    public function addComment(AddCommentTaskRequest $request, Task $task): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validated();

        return (new TaskCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }

    public function today(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $timezone = currentCompany()->timezone;
        $today = Carbon::now($timezone)->toDateString();

        $query = Task::query()
            ->where('company_id', $actor->company_id)
            ->whereDate('due_date', $today);

        if (! $actor->isManager()) {
            $query->whereJsonContains('assigned_to', $actor->id);
        } elseif ($request->filled('assigned_to')) {
            $query->whereJsonContains('assigned_to', $request->integer('assigned_to'));
        }

        return TaskResource::collection(
            $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                ->orderBy('due_date')
                ->get()
        )->response();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCompletionMetrics(Task $task, array &$data): void
    {
        if (($data['status'] ?? null) !== 'done') {
            return;
        }

        $data['completed_at'] = $task->completed_at ?? now('UTC');

        $estimated = (int) ($data['estimated_minutes'] ?? $task->estimated_minutes ?? 0);
        $completed = (int) ($data['completed_minutes'] ?? $task->completed_minutes ?? 0);
        if ($estimated > 0 && $completed > 0) {
            $ratio = max(0.0, min(2.0, $estimated / $completed));
            $data['performance_score'] = round($ratio * 50, 2);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterWritableTaskColumns(array $data): array
    {
        foreach (['category', 'checklist', 'visibility'] as $column) {
            if (array_key_exists($column, $data) && ! Schema::hasColumn('tasks', $column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }
}
