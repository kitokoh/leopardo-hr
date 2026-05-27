<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $request->validate(['status' => ['nullable', 'in:active,completed,archived'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $query = Project::query();
        if (! $actor->isManager()) {
            $query->whereJsonContains('members', $actor->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 15);

        return ProjectResource::collection($query->orderByDesc('created_at')->paginate($perPage))
            ->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d', 'gte:start_date'], 'members' => ['nullable', 'array'], 'members.*' => ['integer', 'min:1'], 'status' => ['nullable', 'in:active,completed,archived']]);
        $project = Project::create(['company_id' => $actor->company_id, 'created_by' => $actor->id, 'members' => $data['members'] ?? [], 'status' => $data['status'] ?? 'active', ...$data]);

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($project->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && ! in_array($actor->id, $project->members ?? [])) {
            abort(403);
        }

        return (new ProjectResource($project))->response();
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($project->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validate(['name' => ['sometimes', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'start_date' => ['nullable', 'date_format:Y-m-d'], 'end_date' => ['nullable', 'date_format:Y-m-d'], 'members' => ['nullable', 'array'], 'members.*' => ['integer', 'min:1'], 'status' => ['nullable', 'in:active,completed,archived']]);
        $project->update($data);

        return (new ProjectResource($project->fresh()))->response();
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($project->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }
}
