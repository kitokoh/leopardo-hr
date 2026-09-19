<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthBedRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthBedRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des lits — HC-002 (#7786, BC-30). Un lit appartient à une salle ;
 * statut libre/occupé/maintenance.
 */
class HealthBedController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthBed::class);

        $query = HealthBed::query()->where('company_id', $actor->company_id);

        if ($request->filled('room_id')) {
            $query->where('room_id', (int) $request->input('room_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $beds = $query->orderBy('code')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($beds->items())->map(fn (HealthBed $bed): array => $this->payload($bed)),
            'meta' => [
                'current_page' => $beds->currentPage(),
                'per_page' => $beds->perPage(),
                'total' => $beds->total(),
            ],
        ]);
    }

    public function store(StoreHealthBedRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        $this->authorize('create', HealthBed::class);

        /** @var HealthBed $bed */
        $bed = HealthBed::query()->create($request->validated());

        return response()->json(['data' => $this->payload($bed)], 201);
    }

    public function show(Request $request, HealthBed $bed): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($bed, $actor->company_id);
        $this->authorize('view', $bed);

        return response()->json(['data' => $this->payload($bed)]);
    }

    public function update(UpdateHealthBedRequest $request, HealthBed $bed): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($bed, $actor->company_id);
        $this->authorize('update', $bed);

        $bed->update($request->validated());

        return response()->json(['data' => $this->payload($bed->refresh())]);
    }

    public function destroy(Request $request, HealthBed $bed): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($bed, $actor->company_id);
        $this->authorize('delete', $bed);

        $bed->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthBed $bed): array
    {
        return [
            'id' => (int) $bed->getAttribute('id'),
            'room_id' => (int) $bed->room_id,
            'code' => $bed->code,
            'status' => $bed->status,
        ];
    }
}
