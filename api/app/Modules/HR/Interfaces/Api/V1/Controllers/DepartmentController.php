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
use Illuminate\Support\Collection;

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

        // #3948 : pagination alignée sur le contrat des autres listes.
        $perPage = (int) ($request->input('per_page', 20));
        $departments = $query->orderBy('name')->paginate($perPage);

        return DepartmentResource::collection($departments);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        // Issue #3597 : company_id non mass-assignable — assignation explicite.
        $dept = Department::create($request->validated());
        $dept->company_id = $user->company_id;
        $dept->save();

        return (new DepartmentResource($dept))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Department $department): DepartmentResource
    {
        $this->authorize('view', $department);

        return new DepartmentResource($department->load('manager', 'positions'));
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

        return response()->json(['message' => __('errors.DEPARTMENT_DELETED')]);
    }

    /**
     * GET /departments/{department}/hierarchy — arbre des employés du département
     * (même forme que /org-chart, scopé tenant, RBAC manager).
     * Consommé par les apps mobile HR/Manager (organigramme) — Closes #2633.
     */
    public function hierarchy(Request $request, Department $department): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403);
        }

        // Garde cross-tenant explicite (le scope global BelongsToCompany protège déjà).
        // Comparaison en STRING : `(int)` sur des UUID (company_id) vaut 0 des deux
        // côtés → la garde ne déclenchait JAMAIS 404 pour un département d'un autre
        // tenant quand le scope global était contourné (issue #5201).
        if ((string) $department->company_id !== (string) $user->company_id) {
            abort(404);
        }

        // Employés actifs du département (arbre de management interne).
        $employees = Employee::query()
            ->select(['id', 'company_id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id', 'department_id', 'status', 'photo_path'])
            ->where('company_id', $user->company_id)
            ->where('department_id', $department->id)
            ->where('status', 'active')
            ->get();

        $departmentName = $department->name;

        $rootId = $department->manager_id;

        // Si le manager du département n'appartient pas au département, on l'inclut
        // comme racine pour que l'arbre ait un point d'entrée cohérent.
        if ($rootId !== null && ! $employees->contains('id', $rootId)) {
            /** @var Employee|null $root */
            $root = Employee::query()
                ->select(['id', 'company_id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id', 'department_id', 'status', 'photo_path'])
                ->where('company_id', $user->company_id)
                ->find($rootId);

            if ($root) {
                $employees->push($root);
            }
        }

        $byManager = $employees->groupBy('manager_id')->toBase();

        // Racine de l'arbre : le manager du département (inclus dans $employees
        // ci-dessus s'il n'y était pas), ses enfants = l'équipe du département.
        $root = $rootId !== null ? $employees->firstWhere('id', $rootId) : null;

        if ($root) {
            $tree = [[
                'id' => $root->id,
                'first_name' => $root->first_name,
                'last_name' => $root->last_name,
                'role' => $root->role,
                'manager_role' => $root->manager_role,
                'parent_id' => $root->manager_id,
                'department' => $departmentName,
                'photo_path' => $root->photo_path,
                'children' => $this->buildDepartmentTree($byManager, $root->id, $departmentName),
            ]];
        } else {
            // Pas de manager : tous les employés sans manager sont des racines.
            $tree = $this->buildDepartmentTree($byManager, null, $departmentName);
        }

        return response()->json(['data' => $tree]);
    }

    /**
     * @param  Collection<(int|string), \Illuminate\Database\Eloquent\Collection<int, Employee>>  $employeesByManager
     * @return array<int, array<string, mixed>>
     */
    private function buildDepartmentTree(Collection $employeesByManager, ?int $parentId, string $departmentName): array
    {
        $tree = [];
        $bucketKey = $parentId ?? '';

        foreach ($employeesByManager->get($bucketKey, collect()) as $employee) {
            $tree[] = [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'role' => $employee->role,
                'manager_role' => $employee->manager_role,
                'parent_id' => $employee->manager_id,
                'department' => $departmentName,
                'photo_path' => $employee->photo_path,
                'children' => $this->buildDepartmentTree($employeesByManager, $employee->id, $departmentName),
            ];
        }

        return $tree;
    }
}
