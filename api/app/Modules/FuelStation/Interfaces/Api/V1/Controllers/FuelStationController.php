<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelStationRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelStationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des stations FuelStation (FUEL-011, #5805).
 *
 * CRUD manager tenant-scoped : code unique par tenant, FK composites
 * anti cross-tenant, 404 sûr cross-tenant (fail-closed avant Policy).
 */
class FuelStationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStation::class);

        $query = FuelStation::query()
            ->withCount('sites')
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function (Builder $query) use ($search): Builder {
                return $query->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('code', 'ilike', '%'.$search.'%');
            });
        }

        $stations = $query
            ->orderBy('name')
            ->paginate((int) min($request->integer('per_page', 20), 100));

        return response()->json([
            'data' => $stations->map(fn (FuelStation $station): array => $this->payload($station)),
            'meta' => [
                'current_page' => $stations->currentPage(),
                'last_page' => $stations->lastPage(),
                'total' => $stations->total(),
            ],
        ]);
    }

    public function store(StoreFuelStationRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelStation::class);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'timezone' => $request->input('timezone') ?? 'UTC',
            'status' => $request->input('status') ?? FuelStation::STATUS_ACTIVE,
        ]));

        return response()->json(['data' => $this->payload($station)], 201);
    }

    public function show(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $station);

        return response()->json(['data' => $this->payload($station->loadCount('sites'))]);
    }

    public function update(UpdateFuelStationRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $station);

        $station->update($request->validated());

        return response()->json(['data' => $this->payload($station->refresh())]);
    }

    public function destroy(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $station);

        $station->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelStation $station): array
    {
        return [
            'id' => $station->id,
            'company_id' => $station->company_id,
            'code' => $station->code,
            'name' => $station->name,
            'address' => $station->address,
            'phone' => $station->phone,
            'timezone' => $station->timezone,
            'currency' => $station->currency,
            'status' => $station->status,
            'sites_count' => $station->sites_count ?? null,
            'created_at' => $station->created_at?->toISOString(),
            'updated_at' => $station->updated_at?->toISOString(),
        ];
    }
}
