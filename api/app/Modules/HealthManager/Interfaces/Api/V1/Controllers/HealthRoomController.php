<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HealthManager\Domain\Exceptions\HealthResourceInUseException;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\StoreHealthRoomRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Requests\UpdateHealthRoomRequest;
use App\Modules\HealthManager\Interfaces\Api\V1\Traits\ChecksHealthSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des salles (référentiel structure) — HC-002 (#7786).
 */
class HealthRoomController extends Controller
{
    use ChecksHealthSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', HealthRoom::class);

        $query = HealthRoom::query()->where('company_id', $actor->company_id);

        if ($request->filled('department_id')) {
            $query->where('department_id', (int) $request->input('department_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $rooms = $query->orderBy('name')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($rooms->items())->map(fn (HealthRoom $room): array => $this->payload($room)),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
            ],
        ]);
    }

    public function store(StoreHealthRoomRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', HealthRoom::class);

        /** @var HealthRoom $room */
        $room = HealthRoom::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
        ]));

        return response()->json(['data' => $this->payload($room->refresh())], 201);
    }

    public function show(Request $request, HealthRoom $room): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($room, $actor->company_id);
        $this->authorize('view', $room);

        return response()->json(['data' => $this->payload($room)]);
    }

    public function update(UpdateHealthRoomRequest $request, HealthRoom $room): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($room, $actor->company_id);
        $this->authorize('update', $room);

        $room->update($request->validated());

        return response()->json(['data' => $this->payload($room->refresh())]);
    }

    public function destroy(Request $request, HealthRoom $room): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($room, $actor->company_id);
        $this->authorize('delete', $room);

        // Suppression bloquée tant que des lits sont rattachés à la salle
        // (422 HEALTH_RESOURCE_IN_USE).
        if ($room->beds()->exists()) {
            throw new HealthResourceInUseException;
        }

        $room->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HealthRoom $room): array
    {
        return [
            'id' => (int) $room->getAttribute('id'),
            'department_id' => $room->department_id,
            'name' => $room->name,
            'code' => $room->code,
            'type' => $room->type,
            'status' => $room->status,
        ];
    }
}
