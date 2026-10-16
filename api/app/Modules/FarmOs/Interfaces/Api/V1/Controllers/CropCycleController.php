<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\CropCycle;
use App\Modules\FarmOs\Domain\Models\Parcel;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Farm OS — Cycles culturaux (historique des cultures par parcelle).
 */
final class CropCycleController
{
    public function __construct(
        private readonly FarmCapabilityGate $gate,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::ParcelsCrops);

        $cycles = CropCycle::query()
            ->with(['parcel:id,name'])
            ->when($request->filled('parcel_id'), fn ($query) => $query->where('parcel_id', $request->integer('parcel_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('id')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $cycles->getCollection()->map(fn (CropCycle $cycle): array => $this->payload($cycle))->all(),
            'meta' => ['current_page' => $cycles->currentPage(), 'last_page' => $cycles->lastPage(), 'total' => $cycles->total()],
        ]);
    }

    public function store(Request $request, Parcel $parcel): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::ParcelsCrops);

        $validated = $request->validate([
            'crop_code' => ['required', 'string', 'max:60'],
            'crop_variety' => ['nullable', 'string', 'max:80'],
            'season_label' => ['nullable', 'string', 'max:40'],
            'planned_sowing_on' => ['nullable', 'date'],
            'actual_sowing_on' => ['nullable', 'date'],
            'planned_harvest_on' => ['nullable', 'date'],
            'expected_yield_value' => ['nullable', 'numeric', 'min:0'],
            'expected_yield_unit' => ['nullable', 'string', 'max:16'],
            'status' => ['nullable', Rule::in(CropCycle::STATUSES)],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $cycle = CropCycle::query()->create(array_merge($validated, [
            'company_id' => $this->company()->id,
            'parcel_id' => $parcel->id,
            'status' => $validated['status'] ?? 'planned',
        ]));

        return response()->json(['data' => $this->payload($cycle)], 201);
    }

    public function close(CropCycle $cycle, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(CropCycle::CLOSED_STATUSES)],
            'actual_harvest_on' => ['nullable', 'date'],
            'actual_yield_value' => ['nullable', 'numeric', 'min:0'],
            'actual_yield_unit' => ['nullable', 'string', 'max:16'],
        ]);

        $cycle->fill($validated);
        $cycle->save();

        return response()->json(['data' => $this->payload($cycle->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CropCycle $cycle): array
    {
        return [
            'id' => $cycle->id,
            'parcel_id' => $cycle->parcel_id,
            'crop_code' => $cycle->crop_code,
            'crop_variety' => $cycle->crop_variety,
            'season_label' => $cycle->season_label,
            'status' => $cycle->status,
            'is_closed' => $cycle->isClosed(),
            'planned_sowing_on' => $cycle->planned_sowing_on?->toDateString(),
            'actual_sowing_on' => $cycle->actual_sowing_on?->toDateString(),
            'planned_harvest_on' => $cycle->planned_harvest_on?->toDateString(),
            'actual_harvest_on' => $cycle->actual_harvest_on?->toDateString(),
            'expected_yield' => $cycle->expected_yield_value === null ? null : [
                'value' => $cycle->expected_yield_value,
                'unit' => $cycle->expected_yield_unit,
            ],
            'actual_yield' => $cycle->actual_yield_value === null ? null : [
                'value' => $cycle->actual_yield_value,
                'unit' => $cycle->actual_yield_unit,
            ],
        ];
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = currentCompany();

        return $company;
    }
}
