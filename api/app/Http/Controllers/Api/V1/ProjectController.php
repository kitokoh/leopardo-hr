<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Project\ProjectIndexRequest;
use App\Http\Requests\Api\V1\Project\StoreProjectRequest;
use App\Http\Requests\Api\V1\Project\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(ProjectIndexRequest $request): JsonResponse
    {
        $actor = $request->user();

        $query = Project::query();
        if (! $actor->isManager()) {
            $query->whereJsonContains('members', $actor->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->integer('per_page', 15);
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($p) => $this->serialize($p)),
            'meta' => ['current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total()],
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validated();
        $project = Project::create(['company_id' => $actor->company_id, 'created_by' => $actor->id, 'members' => $data['members'] ?? [], 'status' => $data['status'] ?? 'active', ...$data]);

        return response()->json(['data' => $this->serialize($project)], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $actor = $request->user();
        if ($project->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && ! in_array($actor->id, $project->members ?? [])) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($project)]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $actor = $request->user();
        if ($project->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $data = $request->validated();
        $project->update($data);

        return response()->json(['data' => $this->serialize($project->fresh())]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
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

    private function serialize(Project $p): array
    {
        return ['id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'start_date' => $p->start_date?->toDateString(), 'end_date' => $p->end_date?->toDateString(), 'members' => $p->members, 'status' => $p->status, 'created_by' => $p->created_by, 'created_at' => $p->created_at?->toIso8601String()];
    }
}
