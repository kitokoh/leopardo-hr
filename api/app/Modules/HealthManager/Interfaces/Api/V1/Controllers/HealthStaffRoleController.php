<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthStaffRoleRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des rôles opérationnels non médicaux (réception, facturation) —
 * HC-002 (#7786).
 */
class HealthStaffRoleController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthStaffRole::class);

        $query = HealthStaffRole::query()->where('company_id', $actor->company_id);

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->input('employee_id'));
        }

        $staffRoles = $query->orderBy('id')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($staffRoles->items())->map(fn (HealthStaffRole $staffRole): array => $this->payload($staffRole)),
            'meta' => [
                'current_page' => $staffRoles->currentPage(),
                'per_page' => $staffRoles->perPage(),
                'total' => $staffRoles->total(),
            ],
        ]);
    }

    public function store(StoreHealthStaffRoleRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthStaffRole::class);

        /** @var HealthStaffRole $staffRole */
        $staffRole = HealthStaffRole::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
        ]));

        return response()->json(['data' => $this->payload($staffRole)], 201);
    }

    public function destroy(Request $request, HealthStaffRole $staffRole): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($staffRole, $actor->company_id);
        $this->authorize('delete', $staffRole);

        $staffRole->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthStaffRole $staffRole): array
    {
        return [
            'id' => (int) $staffRole->getAttribute('id'),
            'employee_id' => $staffRole->employee_id,
            'role' => $staffRole->role,
        ];
    }
}
