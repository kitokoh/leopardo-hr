<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelSiteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des sites opérationnels FuelStation (FUEL-011, #5805).
 *
 * CRUD manager nested sous une station (`/fuel-station/stations/{station}/sites`),
 * tenant-scoped via la FK composite (station_id, company_id).
 */
class FuelSiteController extends Controller
{
    public function index(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('viewAny', FuelSite::class);

        $sites = FuelSite::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $sites->map(fn (FuelSite $site): array => $this->payload($site)),
        ]);
    }

    public function store(StoreFuelSiteRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('create', FuelSite::class);

        /** @var FuelSite $site */
        $site = FuelSite::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'station_id' => $station->id,
            'status' => $request->input('status') ?? 'active',
        ]));

        return response()->json(['data' => $this->payload($site)], 201);
    }

    public function show(Request $request, FuelSite $site): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($site->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $site);

        return response()->json(['data' => $this->payload($site)]);
    }

    public function update(StoreFuelSiteRequest $request, FuelSite $site): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($site->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $site);

        $site->update($request->validated());

        return response()->json(['data' => $this->payload($site->refresh())]);
    }

    public function destroy(Request $request, FuelSite $site): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($site->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $site);

        $site->delete();

        return response()->json(null, 204);
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
}
