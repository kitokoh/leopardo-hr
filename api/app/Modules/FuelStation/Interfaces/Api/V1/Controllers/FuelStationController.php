<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelSiteRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelStationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Référentiel stations & sites FuelStation (FUEL-011, #5805).
 *
 * Manager + solution active (fail-closed) + tenant-scoped (404
 * cross-tenant). Tri/filtres allowlist, pagination bornée (1..100).
 */
class FuelStationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelStation::class);

        $query = FuelStation::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $search = $request->input('search');
        if (is_string($search) && $search !== '') {
            $query->where(fn ($q): object => $q
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%"));
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy(in_array($sort, ['name', 'code', 'created_at'], true) ? $sort : 'name', $direction);

        $stations = $query->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($stations->items())->map(fn (FuelStation $station): array => $this->payload($station)),
            'meta' => [
                'current_page' => $stations->currentPage(),
                'last_page' => $stations->lastPage(),
                'total' => $stations->total(),
            ],
        ]);
    }

    public function store(SaveFuelStationRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelStation::class);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $actor->company_id,
            ...$request->validated(),
        ]);

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

        return response()->json(['data' => $this->payload($station->load('sites'))]);
    }

    public function update(SaveFuelStationRequest $request, FuelStation $station): JsonResponse
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

    public function sitesIndex(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $station);

        $sites = FuelSite::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $sites->map(fn (FuelSite $site): array => $this->sitePayload($site))]);
    }

    public function sitesStore(SaveFuelSiteRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelStation::class);

        /** @var FuelSite $site */
        $site = FuelSite::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => $this->sitePayload($site)], 201);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /** @return array<string, mixed> */
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
            'sites_count' => $station->sites_count ?? null,
            'created_at' => $station->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function sitePayload(FuelSite $site): array
    {
        return [
            'id' => $site->id,
            'station_id' => $site->station_id,
            'code' => $site->code,
            'name' => $site->name,
            'address' => $site->address,
            'status' => $site->status,
            'created_at' => $site->created_at?->toISOString(),
        ];
    }
}
