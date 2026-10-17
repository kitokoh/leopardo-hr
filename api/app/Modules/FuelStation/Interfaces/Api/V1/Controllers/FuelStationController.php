<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationLogoService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\SaveFuelSiteRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelStationRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\UpdateFuelStationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD des stations FuelStation (FUEL-011, issue #5805).
 *
 * deny-by-default (FuelStationPolicy) : CRUD manager, lecture employé du
 * tenant. Isolation tenant fail-closed : cross-tenant → 404. Filtres
 * allowlist (status, q), pagination bornée (1..100).
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

        $search = $request->input('q');
        if (is_string($search) && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('code', 'ilike', '%'.$search.'%');
            });
        }

        $stations = $query->orderBy('name')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($stations->items())->map(fn (FuelStation $s): array => $this->payload($s)),
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

        $station = FuelStation::query()->create([
            'company_id' => $actor->company_id,
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'phone' => $request->input('phone'),
            'timezone' => $request->input('timezone', 'UTC'),
            'currency' => $request->input('currency'),
            'status' => $request->input('status', 'active'),
            'metadata' => $request->input('metadata'),
        ]);

        return response()->json(['data' => $this->payload($station->refresh())], 201);
    }

    public function show(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $station);

        return response()->json(['data' => $this->payload($station)]);
    }

    public function update(UpdateFuelStationRequest $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== (string) $actor->company_id) {
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

        if ($station->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('delete', $station);

        // Enveloppe uniforme de suppression du referentiel (§B-2 du lot 2) :
        // le referentiel generique renvoie deja {id, deleted:true}. La station
        // faisait exception -- contrat incoherent entre deux routes de la meme
        // famille. Aucune suppression physique : la station est archivee.
        $station->update(['status' => 'archived']);

        return response()->json(['data' => ['id' => $station->id, 'status' => 'archived', 'deleted' => true]]);
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
            'logo_url' => $station->logo_url,
            'status' => $station->status,
            'created_at' => $station->created_at?->toIso8601String(),
            'updated_at' => $station->updated_at?->toIso8601String(),
        ];
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /**
     * P1-e (audit 2026-10-17) - upload du logo de station.
     *
     * POST /fuel-station/stations/{station}/logo - manager. Reutilise le
     * stockage du branding societe (disque `public`, chemin par tenant) :
     * aucun stockage parallele. Le logo d'un autre tenant est indiscernable
     * d'un inexistant (404).
     */
    public function uploadLogo(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $station);

        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('logo');

        $station = app(FuelStationLogoService::class)->store($station, $file);

        return response()->json(['data' => $this->payload($station)]);
    }

    /**
     * P1-e - suppression du logo de station (DELETE /fuel-station/stations/{station}/logo).
     */
    public function deleteLogo(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $station);

        $station = app(FuelStationLogoService::class)->delete($station);

        return response()->json(['data' => $this->payload($station)]);
    }

    public function sitesIndex(Request $request, FuelStation $station): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($station->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $station);

        $sites = FuelSite::query()
            ->where('company_id', $actor->company_id)
            ->where('station_id', $station->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $sites->map(fn (FuelSite $site): array => $this->sitePayload($site)),
        ]);
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

    private function sitePayload(FuelSite $site): array
    {
        return [
            'id' => $site->id,
            'station_id' => $site->station_id,
            'code' => $site->code,
            'name' => $site->name,
            'address' => $site->address,
            'status' => $site->status,
            'created_at' => $site->created_at?->toIso8601String(),
        ];
    }
}
