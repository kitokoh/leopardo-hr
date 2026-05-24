<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Department\StoreDepartmentRequest;
use App\Http\Requests\Api\V1\Department\UpdateDepartmentRequest;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepartmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        $departments = Department::query()
            ->select(['id', 'company_id', 'name', 'manager_id', 'created_at'])
            ->with('manager:id,first_name,last_name')
            ->orderBy('name')
            ->get();

        return DepartmentResource::collection($departments);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $dept = Department::create(['company_id' => $user->company_id, ...$request->validated()]);

        return (new DepartmentResource($dept))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Department $department): DepartmentResource
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        return new DepartmentResource($department->load('manager', 'positions'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $department->update($request->validated());

        return new DepartmentResource($department->fresh());
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
}
