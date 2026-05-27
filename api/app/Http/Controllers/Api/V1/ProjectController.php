<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\Project\ProjectIndexRequest;
use App\Http\Requests\Api\V1\Project\StoreProjectRequest;
use App\Http\Requests\Api\V1\Project\UpdateProjectRequest;

class ProjectController extends Controller
{
    public function index(ProjectIndexRequest $request): JsonResponse
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

    public function store(StoreProjectRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validated();

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

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($project->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validated();
    }
}
