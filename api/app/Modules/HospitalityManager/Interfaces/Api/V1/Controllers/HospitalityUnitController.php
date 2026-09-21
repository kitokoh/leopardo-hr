<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityUnit;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\StoreHospitalityUnitRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\UpdateHospitalityUnitRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des unités physiques (chambres / appartements) — HOSP-002 (#7944).
 *
 * Index/création imbriqués sous l'établissement ; mise à jour et
 * suppression en route « shallow » (`/units/{unit}`) — tenant TOUJOURS
 * re-vérifié (404 fail-closed).
 */
class HospitalityUnitController extends Controller
{
    use ChecksHospitalitySolution;

    public function index(Request $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        // Liste imbriquée : lecture scopée à CET établissement (RBAC
        // ressource-scopé progressif — voir ChecksHospitalityPropertyAccess).
        $this->authorize('view', $property);

        $query = HospitalityUnit::query()
            ->where('company_id', $actor->company_id)
            ->where('property_id', $property->getKey());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', (int) $request->input('room_type_id'));
        }

        $units = $query->orderBy('code')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($units->items())->map(fn (HospitalityUnit $unit): array => $this->payload($unit)),
            'meta' => [
                'current_page' => $units->currentPage(),
                'per_page' => $units->perPage(),
                'total' => $units->total(),
            ],
        ]);
    }

    public function store(StoreHospitalityUnitRequest $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('create', [HospitalityUnit::class, $property->getKey()]);

        /** @var HospitalityUnit $unit */
        $unit = HospitalityUnit::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'property_id' => $property->getKey(),
        ]));

        return response()->json(['data' => $this->payload($unit->refresh())], 201);
    }

    public function update(UpdateHospitalityUnitRequest $request, HospitalityUnit $unit): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($unit, $actor->company_id);
        $this->authorize('update', $unit);

        $unit->update($request->validated());

        return response()->json(['data' => $this->payload($unit->refresh())]);
    }

    public function destroy(Request $request, HospitalityUnit $unit): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($unit, $actor->company_id);
        $this->authorize('delete', $unit);

        $unit->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HospitalityUnit $unit): array
    {
        return [
            'id' => (int) $unit->getAttribute('id'),
            'property_id' => (int) $unit->getAttribute('property_id'),
            'room_type_id' => $unit->room_type_id,
            'code' => $unit->code,
            'floor' => $unit->floor,
            'status' => $unit->status,
            'notes' => $unit->notes,
        ];
    }
}
