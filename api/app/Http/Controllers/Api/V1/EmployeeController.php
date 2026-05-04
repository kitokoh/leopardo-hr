<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CreateEmployeeDTO;
use App\DTOs\UpdateEmployeeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ArchiveEmployeeRequest;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $perPage = max(1, min(100, (int) request()->integer('per_page', 20)));
        $paginator = Employee::query()
            ->with(['company:id,name,language,timezone,currency,features'])
            ->select([
                'id',
                'matricule',
                'company_id',
                'first_name',
                'last_name',
                'email',
                'role',
                'manager_role',
                'status',
                'photo_path',
                'contract_start',
                'preferred_language',
                'extra_data',
            ])
            ->orderBy('id')
            ->paginate($perPage);

        return EmployeeResource::collection($paginator)->response();
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $employee = $this->employeeService->create(CreateEmployeeDTO::fromRequest($request), $actor);

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $employeeId, Request $request): JsonResponse
    {
        $employee = Employee::query()
            ->with(['company:id,name,language,timezone,currency,features'])
            ->findOrFail($employeeId);

        $this->authorize('view', $employee);

        return (new EmployeeResource($employee))->response();
    }

    public function update(UpdateEmployeeRequest $request, string $employeeId): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $employee = Employee::query()->findOrFail($employeeId);

        $this->authorize('update', $employee);

        $employee = $this->employeeService->update($actor, $employee, UpdateEmployeeDTO::fromRequest($request));

        return (new EmployeeResource($employee))->response();
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
            'message' => 'EMPLOYEE_ARCHIVED',
        ]);
    }
}
