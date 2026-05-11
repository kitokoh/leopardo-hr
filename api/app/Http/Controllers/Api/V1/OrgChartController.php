<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgChartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->select(['id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id', 'status', 'photo_path'])
            ->with('schedule:id,name')
            ->where('status', 'active')
            ->get();

        $tree = $this->buildTree($employees, null);

        return response()->json(['data' => $tree]);
    }

    public function subordinates(Request $request, int $employeeId): JsonResponse
    {
        $actor = $request->user();

        $directReports = Employee::query()
            ->select(['id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id', 'email', 'status'])
            ->where('manager_id', $employeeId)
            ->where('status', 'active')
            ->get();

        return response()->json(['data' => $directReports]);
    }

    public function managerChain(Request $request, int $employeeId): JsonResponse
    {
        $chain = [];
        $current = Employee::select(['id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id'])->find($employeeId);

        if (! $current) {
            abort(404);
        }

        $visited = [];
        while ($current->manager_id !== null && ! in_array($current->manager_id, $visited, true)) {
            $visited[] = $current->manager_id;
            $manager = Employee::select(['id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id'])->find($current->manager_id);
            if (! $manager) {
                break;
            }
            $chain[] = [
                'id' => $manager->id,
                'first_name' => $manager->first_name,
                'last_name' => $manager->last_name,
                'role' => $manager->role,
                'manager_role' => $manager->manager_role,
            ];
            $current = $manager;
        }

        return response()->json(['data' => $chain]);
    }

    private function buildTree($employees, ?int $parentId): array
    {
        $tree = [];
        foreach ($employees->where('manager_id', $parentId) as $employee) {
            $tree[] = [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'role' => $employee->role,
                'manager_role' => $employee->manager_role,
                'photo_path' => $employee->photo_path,
                'children' => $this->buildTree($employees, $employee->id),
            ];
        }

        return $tree;
    }
}
