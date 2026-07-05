<?php

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreDepartmentRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateDepartmentRequest;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Modules\HR\Domain\Models\Department;
use App\Core\Auth\Domain\Models\Employee;
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

