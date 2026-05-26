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

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $request->validate(['project_id' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', 'in:todo,inprogress,review,done,rejected,cancelled'], 'priority' => ['nullable', 'in:low,normal,high,urgent'], 'assigned_to' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $query = Task::query();

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
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'assigned_to' => ['nullable', 'array'], 'assigned_to.*' => ['integer', 'min:1'], 'project_id' => ['nullable', 'integer', 'min:1'], 'due_date' => ['required', 'date'], 'priority' => ['nullable', 'in:low,normal,high,urgent'], 'category' => ['nullable', 'string', 'max:100'], 'visibility' => ['nullable', 'in:private,visible'], 'checklist' => ['nullable', 'array']]);

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

        $data = $request->validate(['title' => ['sometimes', 'string', 'max:200'], 'description' => ['nullable', 'string'], 'assigned_to' => ['sometimes', 'array'], 'assigned_to.*' => ['integer', 'min:1'], 'project_id' => ['nullable', 'integer', 'min:1'], 'due_date' => ['sometimes', 'date'], 'priority' => ['sometimes', 'in:low,normal,high,urgent'], 'status' => ['sometimes', 'in:todo,inprogress,review,done,rejected,cancelled'], 'category' => ['nullable', 'string', 'max:100'], 'visibility' => ['sometimes', 'in:private,visible'], 'checklist' => ['nullable', 'array']]);
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

}
