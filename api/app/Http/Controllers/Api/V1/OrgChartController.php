<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class OrgChartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $employees = Employee::query()
            ->select(['id', 'company_id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id', 'status', 'photo_path'])
            ->with('schedule:id,name')
            ->where('company_id', $actor->company_id)
            ->where('status', 'active')
            ->get();

        $tree = $this->buildTree($employees->groupBy('manager_id'), null);

        return response()->json(['data' => $tree]);
    }

    public function subordinates(Request $request, int $employeeId): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $directReports = Employee::query()
            ->select(['id', 'company_id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id', 'email', 'status'])
            ->where('company_id', $actor->company_id)
            ->where('manager_id', $employeeId)
            ->where('status', 'active')
            ->get();

        return response()->json(['data' => $directReports]);
    }

    public function managerChain(Request $request, int $employeeId): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $chain = [];
        $current = Employee::query()
            ->select(['id', 'company_id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id'])
            ->where('company_id', $actor->company_id)
            ->find($employeeId);

        if (! $current) {
            abort(404);
        }

        $visited = [];
        while ($current->manager_id !== null && ! in_array($current->manager_id, $visited, true)) {
            $visited[] = $current->manager_id;
            $manager = Employee::query()
                ->select(['id', 'company_id', 'first_name', 'last_name', 'role', 'manager_role', 'manager_id'])
                ->where('company_id', $actor->company_id)
                ->find($current->manager_id);
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

    /**
     * @param  Collection<int|string, Collection<int, Employee>>  $employeesByManager
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $employeesByManager, ?int $parentId): array
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
                'photo_path' => $employee->photo_path,
                'children' => $this->buildTree($employeesByManager, $employee->id),
            ];
        }

        return $tree;
    }
}
