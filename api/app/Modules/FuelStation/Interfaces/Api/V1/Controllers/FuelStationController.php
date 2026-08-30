<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelStationRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelStationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5805 — CRUD des stations FuelStation (FUEL-011).
 *
 * Tenant-scoped, feature flag `fuel_station` (solution inactive → 403),
 * Policies deny-by-default (manager écrit, tout employé lit).
 */
class FuelStationController extends Controller
{
    use FuelIndexQueryTrait;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStation::class);

        $query = FuelStation::query()->where('company_id', $actor->company_id);

        $stations = $this->applyFuelIndexQuery($query, $request, ['name', 'code', 'created_at'], ['status']);

        return response()->json(['data' => $stations->through(fn (FuelStation $station): array => $this->payload($station))]);
    }

    public function store(StoreFuelStationRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelStation::class);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create($request->validated() + ['company_id' => $actor->company_id]);

        return response()->json(['data' => $this->payload($station)], 201);
    }

    public function show(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($station, $request->user());
        $this->authorize('view', $station);

        return response()->json(['data' => $this->payload($station->loadMissing(['pumps', 'tanks']))]);
    }

    public function update(UpdateFuelStationRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($station, $request->user());
        $this->authorize('update', $station);

        $station->update($request->validated());

        return response()->json(['data' => $this->payload($station->fresh())]);
    }

    public function destroy(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();
        $this->guardTenant($station, $request->user());
        $this->authorize('delete', $station);

        $station->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function guardTenant(FuelStation $station, mixed $actor): void
    {
        if ($actor instanceof Employee && (string) $station->company_id !== (string) $actor->company_id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelStation $station): array
    {
        return [
            'id' => $station->id,
            'code' => $station->code,
            'name' => $station->name,
            'address' => $station->address,
            'phone' => $station->phone,
            'timezone' => $station->timezone,
            'currency' => $station->currency,
            'status' => $station->status,
            'created_at' => optional($station->created_at)->toIso8601String(),
            'pumps_count' => $station->relationLoaded('pumps') ? $station->pumps->count() : null,
            'tanks_count' => $station->relationLoaded('tanks') ? $station->tanks->count() : null,
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
