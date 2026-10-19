<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Application\Actions\CreateParcelAction;
use App\Modules\FarmOs\Domain\Enums\AreaSource;
use App\Modules\FarmOs\Domain\Enums\AreaUnit;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Exceptions\FarmAreaMeasurementException;
use App\Modules\FarmOs\Domain\Models\FarmSite;
use App\Modules\FarmOs\Domain\Models\Parcel;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Farm OS — Parcelles (liste, création, détail, historique).
 *
 * Exigence d'honnêteté : la vue cartographique n'est exposée que si une
 * géométrie existe réellement (`geometry_available`) ; la superficie porte
 * toujours sa provenance.
 */
final class ParcelController
{
    public function __construct(
        private readonly CreateParcelAction $createParcel,
        private readonly FarmCapabilityGate $gate,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::ParcelsCrops);

        $parcels = Parcel::query()
            ->with(['farmSite:id,name'])
            ->when($request->filled('farm_site_id'), fn ($query) => $query->where('farm_site_id', $request->integer('farm_site_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $parcels->getCollection()->map(fn (Parcel $parcel): array => $this->payload($parcel))->all(),
            'meta' => ['current_page' => $parcels->currentPage(), 'last_page' => $parcels->lastPage(), 'total' => $parcels->total()],
        ]);
    }

    public function store(Request $request, FarmSite $site): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::ParcelsCrops);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:40', 'alpha_dash'],
            'area_value' => ['nullable', 'numeric', 'min:0'],
            'area_unit' => ['nullable', Rule::in(array_map(static fn (AreaUnit $u): string => $u->value, AreaUnit::cases()))],
            'area_source' => ['nullable', Rule::in(array_map(static fn (AreaSource $s): string => $s->value, AreaSource::cases()))],
            'geometry_wkt' => ['nullable', 'string', 'max:200000'],
            'geometry_source' => ['nullable', Rule::in(['drawn', 'imported'])],
            'soil_type' => ['nullable', 'string', 'max:60'],
            'irrigation_type' => ['nullable', Rule::in(['rainfed', 'drip', 'sprinkler', 'furrow', 'other'])],
            'status' => ['nullable', Rule::in(Parcel::STATUSES)],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        try {
            $parcel = $this->createParcel->execute($this->company(), $site, $validated, $request->user()?->id);
        } catch (FarmAreaMeasurementException $exception) {
            return response()->json(['error' => 'INVALID_AREA', 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->payload($parcel)], 201);
    }

    public function show(Parcel $parcel): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::ParcelsCrops);

        return response()->json(['data' => $this->payload($parcel)]);
    }

    public function history(Parcel $parcel): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::ParcelsCrops);

        $cycles = $parcel->cropCycles()->orderByDesc('actual_sowing_on')->get();
        $operations = $parcel->operations()->orderByDesc('planned_start_at')->limit(50)->get();

        return response()->json([
            'data' => [
                'crop_cycles' => $cycles->map(static fn ($cycle): array => [
                    'id' => $cycle->id,
                    'crop_code' => $cycle->crop_code,
                    'crop_variety' => $cycle->crop_variety,
                    'status' => $cycle->status,
                    'actual_sowing_on' => $cycle->actual_sowing_on?->toDateString(),
                    'actual_harvest_on' => $cycle->actual_harvest_on?->toDateString(),
                ])->all(),
                'operations' => $operations->map(static fn ($operation): array => [
                    'id' => $operation->id,
                    'op_type' => $operation->op_type,
                    'title' => $operation->title,
                    'status' => $operation->status,
                    'planned_start_at' => $operation->planned_start_at?->toIso8601String(),
                    'finished_at' => $operation->finished_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Parcel $parcel): array
    {
        return [
            'id' => $parcel->id,
            'farm_site_id' => $parcel->farm_site_id,
            'name' => $parcel->name,
            'reference' => $parcel->reference,
            'status' => $parcel->status,
            'soil_type' => $parcel->soil_type,
            'irrigation_type' => $parcel->irrigation_type,
            // Aucune donnée inventée : valeur absente ⇒ null, et la provenance
            // accompagne toujours la superficie.
            'area' => $parcel->area_value === null ? null : [
                'value' => $parcel->area_value,
                'unit' => $parcel->area_unit,
                'source' => $parcel->area_source,
            ],
            // La carte n'est proposée que si une géométrie existe vraiment.
            'geometry_available' => $parcel->hasGeometry(),
            'created_at' => $parcel->created_at?->toIso8601String(),
        ];
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
