<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthSpecialty;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthSpecialtyRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthSpecialtyRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des spécialités médicales — HC-002 (#7786, BC-30).
 */
class HealthSpecialtyController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthSpecialty::class);

        $query = HealthSpecialty::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $specialties = $query->orderBy('name')->paginate((int) ($request->input('per_page') ?? 50));

        return response()->json([
            'data' => collect($specialties->items())->map(fn (HealthSpecialty $specialty): array => $this->payload($specialty)),
            'meta' => [
                'current_page' => $specialties->currentPage(),
                'per_page' => $specialties->perPage(),
                'total' => $specialties->total(),
            ],
        ]);
    }

    public function store(StoreHealthSpecialtyRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        $this->authorize('create', HealthSpecialty::class);

        /** @var HealthSpecialty $specialty */
        $specialty = HealthSpecialty::query()->create($request->validated());

        return response()->json(['data' => $this->payload($specialty)], 201);
    }

    public function show(Request $request, HealthSpecialty $specialty): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($specialty, $actor->company_id);
        $this->authorize('view', $specialty);

        return response()->json(['data' => $this->payload($specialty)]);
    }

    public function update(UpdateHealthSpecialtyRequest $request, HealthSpecialty $specialty): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($specialty, $actor->company_id);
        $this->authorize('update', $specialty);

        $specialty->update($request->validated());

        return response()->json(['data' => $this->payload($specialty->refresh())]);
    }

    public function destroy(Request $request, HealthSpecialty $specialty): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($specialty, $actor->company_id);
        $this->authorize('delete', $specialty);

        $specialty->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthSpecialty $specialty): array
    {
        return [
            'id' => (int) $specialty->getAttribute('id'),
            'code' => $specialty->code,
            'name' => $specialty->name,
            'status' => $specialty->status,
        ];
    }
}
