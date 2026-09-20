<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Exceptions\HealthResourceInUseException;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthDepartmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthDepartmentRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des services médicaux (référentiel structure) — HC-002 (#7786).
 */
class HealthDepartmentController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthDepartment::class);

        $query = HealthDepartment::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $departments = $query->orderBy('name')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($departments->items())->map(fn (HealthDepartment $department): array => $this->payload($department)),
            'meta' => [
                'current_page' => $departments->currentPage(),
                'per_page' => $departments->perPage(),
                'total' => $departments->total(),
            ],
        ]);
    }

    public function store(StoreHealthDepartmentRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthDepartment::class);

        /** @var HealthDepartment $department */
        $department = HealthDepartment::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
        ]));

        return response()->json(['data' => $this->payload($department->refresh())], 201);
    }

    public function show(Request $request, HealthDepartment $department): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($department, $actor->company_id);
        $this->authorize('view', $department);

        return response()->json(['data' => $this->payload($department)]);
    }

    public function update(UpdateHealthDepartmentRequest $request, HealthDepartment $department): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($department, $actor->company_id);
        $this->authorize('update', $department);

        $department->update($request->validated());

        return response()->json(['data' => $this->payload($department->refresh())]);
    }

    public function destroy(Request $request, HealthDepartment $department): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($department, $actor->company_id);
        $this->authorize('delete', $department);

        // Suppression bloquée tant que des salles ou des praticiens sont
        // rattachés au service (422 HEALTH_RESOURCE_IN_USE).
        if ($department->rooms()->exists() || $department->practitioners()->exists()) {
            throw new HealthResourceInUseException;
        }

        $department->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthDepartment $department): array
    {
        return [
            'id' => (int) $department->getAttribute('id'),
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description,
            'status' => $department->status,
        ];
    }
}
