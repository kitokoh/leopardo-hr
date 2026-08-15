<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreDepartmentRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateDepartmentRequest;
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

        $query = Department::query()->with('manager:id,first_name,last_name');

        if ($user->isDepartmentScoped()) {
            // manager_role=dept is scoped to their own department only (PA2-SEC-002).
            $query->where('id', $user->department_id ?? -1);
        }

        $departments = $query->orderBy('name')->get();

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
        $this->authorize('view', $department);

        return new DepartmentResource($department->load('manager', 'positions'));
    }

    /**
     * Audit expert 2026-08-15 (issue #2594) : GET /departments/{department}/hierarchy
     * — l'organigramme par département des apps mobiles (organigramme_repository
     * manager/hr) appelait cet endpoint qui n'existait pas (404). Retourne le
     * département avec son manager et ses employés actifs (scopé tenant).
     */
    public function hierarchy(Request $request, Department $department): JsonResponse
    {
        $this->authorize('view', $department);

        $employees = Employee::query()
            ->where('department_id', $department->id)
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'position_id', 'manager_id']);

        return new JsonResponse([
            'data' => [
                'department' => new DepartmentResource($department->load('manager')),
                'manager' => $department->manager,
                'employees' => $employees,
                'employee_count' => $employees->count(),
            ],
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return new DepartmentResource($department->fresh());
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        $department->delete();

        return response()->json(['message' => 'Department deleted successfully']);
    }
}
