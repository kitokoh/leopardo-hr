<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return response()->json([
            'data' => Department::query()
                ->select(['id', 'company_id', 'name', 'manager_id', 'created_at'])
                ->with('manager:id,first_name,last_name')
                ->orderBy('name')
                ->get()
                ->map(fn ($d) => $this->serialize($d)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'manager_id' => ['nullable', 'integer', 'min:1']]);
        $dept = Department::create(['company_id' => $user->company_id, ...$data]);

        return response()->json(['data' => $this->serialize($dept)], 201);
    }

    public function show(Request $request, Department $department): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return response()->json(['data' => $this->serialize($department->load('manager', 'positions'))]);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $data = $request->validate(['name' => ['sometimes', 'string', 'max:100'], 'manager_id' => ['nullable', 'integer', 'min:1']]);
        $department->update($data);

        return response()->json(['data' => $this->serialize($department->fresh())]);
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully']);
    }

    private function serialize(Department $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'manager_id' => $d->manager_id,
            'manager' => $d->relationLoaded('manager') && $d->manager ? ['id' => $d->manager->id, 'first_name' => $d->manager->first_name, 'last_name' => $d->manager->last_name] : null,
            'positions' => $d->relationLoaded('positions') ? $d->positions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name]) : null,
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
