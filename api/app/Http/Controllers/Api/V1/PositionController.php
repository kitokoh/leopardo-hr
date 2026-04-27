<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        $query = Position::with('department');
        if ($request->filled('department_id')) $query->where('department_id', $request->integer('department_id'));

        return response()->json(['data' => $query->orderBy('name')->get()->map(fn ($p) => $this->serialize($p))]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'department_id' => ['nullable', 'integer', 'min:1']]);
        $pos  = Position::create(['company_id' => $request->user()->company_id, ...$data]);

        return response()->json(['data' => $this->serialize($pos)], 201);
    }

    public function show(Request $request, Position $position): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        return response()->json(['data' => $this->serialize($position->load('department'))]);
    }

    public function update(Request $request, Position $position): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'department_id' => ['nullable', 'integer', 'min:1']]);
        $position->update($data);

        return response()->json(['data' => $this->serialize($position->fresh())]);
    }

    public function destroy(Request $request, Position $position): JsonResponse
    {
        if (!$request->user()->isManager()) abort(403);

        $position->delete();

        return response()->json(['message' => 'Position deleted successfully']);
    }

    private function serialize(Position $p): array
    {
        return [
            'id'            => $p->id,
            'name'          => $p->name,
            'department_id' => $p->department_id,
            'department'    => $p->relationLoaded('department') && $p->department ? ['id' => $p->department->id, 'name' => $p->department->name] : null,
            'created_at'    => $p->created_at?->toIso8601String(),
        ];
    }
}
