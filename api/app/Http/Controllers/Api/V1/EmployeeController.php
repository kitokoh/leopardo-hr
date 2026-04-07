<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArchiveEmployeeRequest;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService)
    {
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $perPage = max(1, min(100, (int) request()->integer('per_page', 20)));
        $paginator = Employee::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'role', 'status'])
            ->orderBy('id')
            ->paginate($perPage);

        return new JsonResponse([
            'data' => collect($paginator->items())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $this->employeeService->create($request->validated());

        return new JsonResponse([
            'data' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'role' => $employee->role,
                'status' => $employee->status,
            ],
        ], 201);
    }

    public function show(string $employeeId, Request $request): JsonResponse
    {
        $employee = Employee::query()->findOrFail($employeeId);

        $this->authorize('view', $employee);

        return new JsonResponse([
            'data' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'role' => $employee->role,
                'status' => $employee->status,
            ],
        ]);
    }

    public function update(UpdateEmployeeRequest $request, string $employeeId): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $employee = Employee::query()->findOrFail($employeeId);

        $this->authorize('update', $employee);

        $employee = $this->employeeService->update($actor, $employee, $request->validated());

        return new JsonResponse([
            'data' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'role' => $employee->role,
                'status' => $employee->status,
            ],
        ]);
    }

    public function archive(ArchiveEmployeeRequest $request, string $employeeId): JsonResponse
    {
        $employee = Employee::query()->findOrFail($employeeId);

        $this->authorize('archive', $employee);

        $employee = $this->employeeService->archive($employee);

        return new JsonResponse([
            'data' => [
                'id' => $employee->id,
                'status' => $employee->status,
            ],
        ]);
    }
}
