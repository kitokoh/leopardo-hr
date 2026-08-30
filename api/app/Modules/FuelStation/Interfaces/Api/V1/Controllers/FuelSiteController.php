<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelSiteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD des sites opérationnels (FUEL-011, #5805) — FK composite
 * (station_id, company_id) → fuel_stations : cross-tenant impossible.
 * deny-by-default (FuelStationPolicy) : CRUD manager.
 */
class FuelSiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelSite::class);

        $query = FuelSite::query()->where('company_id', $actor->company_id);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        $sites = $query->orderBy('name')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($sites->items())->map(fn (FuelSite $s): array => $this->payload($s)),
            'meta' => [
                'current_page' => $sites->currentPage(),
                'last_page' => $sites->lastPage(),
                'total' => $sites->total(),
            ],
        ]);
    }

    public function store(StoreFuelSiteRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelSite::class);

        $site = FuelSite::query()->create([
            'company_id' => $actor->company_id,
            'station_id' => $request->input('station_id'),
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'status' => $request->input('status', 'active'),
            'metadata' => $request->input('metadata'),
        ]);

        return response()->json(['data' => $this->payload($site->refresh())], 201);
    }

    public function show(Request $request, FuelSite $site): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($site->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $site);

        return response()->json(['data' => $this->payload($site)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FuelSite $site): array
    {
        return [
            'id' => $site->id,
            'company_id' => $site->company_id,
            'station_id' => $site->station_id,
            'code' => $site->code,
            'name' => $site->name,
            'address' => $site->address,
            'status' => $site->status,
            'created_at' => $site->created_at?->toISOString(),
            'updated_at' => $site->updated_at?->toISOString(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
