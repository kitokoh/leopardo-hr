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
use Illuminate\Support\Carbon;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
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

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'assigned_to' => ['nullable', 'array'], 'assigned_to.*' => ['integer', 'min:1'], 'project_id' => ['nullable', 'integer', 'min:1'], 'due_date' => ['required', 'date'], 'priority' => ['nullable', 'in:low,normal,high,urgent'], 'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'recurrence_rule' => ['nullable', 'string', 'max:120'], 'template_key' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'string', 'max:100'], 'visibility' => ['nullable', 'in:private,visible'], 'checklist' => ['nullable', 'array']]);

        $task = Task::create(['company_id' => $actor->company_id, 'created_by' => $actor->id, 'assigned_to' => $data['assigned_to'] ?? [], 'status' => 'todo', 'priority' => $data['priority'] ?? 'normal', 'visibility' => $data['visibility'] ?? 'visible', ...$data]);

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

    public function update(Request $request, Task $task): JsonResponse
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

        $data = $request->validate(['title' => ['sometimes', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'assigned_to' => ['sometimes', 'array'], 'assigned_to.*' => ['integer', 'min:1'], 'project_id' => ['nullable', 'integer', 'min:1'], 'due_date' => ['sometimes', 'date'], 'priority' => ['sometimes', 'in:low,normal,high,urgent'], 'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'completed_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'], 'completion_note' => ['nullable', 'string', 'max:1000'], 'recurrence_rule' => ['nullable', 'string', 'max:120'], 'template_key' => ['nullable', 'string', 'max:100'], 'status' => ['sometimes', 'in:todo,inprogress,review,done,rejected,cancelled'], 'category' => ['nullable', 'string', 'max:100'], 'visibility' => ['sometimes', 'in:private,visible'], 'checklist' => ['nullable', 'array']]);
        $this->applyCompletionMetrics($task, $data);
        $task->update($data);

        return (new TaskResource($task->fresh()))->response();
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $task->created_by !== $actor->id) {
            abort(403);
        }

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }

    public function addComment(Request $request, Task $task): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($task->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validate(['content' => ['required', 'string', 'max:5000']]);
        $comment = TaskComment::create(['company_id' => $actor->company_id, 'task_id' => $task->id, 'author_id' => $actor->id, 'content' => $data['content']]);

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
}
