<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityRoomType;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\StoreHospitalityRoomTypeRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\UpdateHospitalityRoomTypeRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\BoundsPagination;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API des types de chambres — HOSP-002 (#7944).
 *
 * Index/création imbriqués sous l'établissement ; lecture unitaire, mise à
 * jour et suppression en route « shallow » (`/room-types/{roomType}`) —
 * le tenant est TOUJOURS re-vérifié (404 fail-closed).
 */
class HospitalityRoomTypeController extends Controller
{
    use BoundsPagination;
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

        $query = HospitalityRoomType::query()
            ->where('company_id', $actor->company_id)
            ->where('property_id', $property->getKey());

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $roomTypes = $query->orderBy('name')->paginate($this->boundedPerPage($request, 15));

        return response()->json([
            'data' => collect($roomTypes->items())->map(fn (HospitalityRoomType $roomType): array => $this->payload($roomType)),
            'meta' => [
                'current_page' => $roomTypes->currentPage(),
                'per_page' => $roomTypes->perPage(),
                'total' => $roomTypes->total(),
            ],
        ]);
    }

    public function store(StoreHospitalityRoomTypeRequest $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('create', [HospitalityRoomType::class, $property->getKey()]);

        /** @var HospitalityRoomType $roomType */
        $roomType = HospitalityRoomType::query()->create(array_merge($request->validated(), [
            'company_id' => $actor->company_id,
            'property_id' => $property->getKey(),
        ]));

        return response()->json(['data' => $this->payload($roomType->refresh())], 201);
    }

    public function update(UpdateHospitalityRoomTypeRequest $request, HospitalityRoomType $roomType): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($roomType, $actor->company_id);
        $this->authorize('update', $roomType);

        $roomType->update($request->validated());

        return response()->json(['data' => $this->payload($roomType->refresh())]);
    }

    public function destroy(Request $request, HospitalityRoomType $roomType): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($roomType, $actor->company_id);
        $this->authorize('delete', $roomType);

        // Un type rattaché à des unités n'est pas supprimable silencieusement.
        if ($roomType->units()->exists()) {
            abort(422, 'HOSPITALITY_ROOM_TYPE_IN_USE');
        }

        $roomType->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HospitalityRoomType $roomType): array
    {
        return [
            'id' => (int) $roomType->getAttribute('id'),
            'property_id' => (int) $roomType->getAttribute('property_id'),
            'name' => $roomType->name,
            'code' => $roomType->code,
            'description' => $roomType->description,
            'capacity_adults' => $roomType->capacity_adults,
            'capacity_children' => $roomType->capacity_children,
            'base_price_minor' => $roomType->base_price_minor,
            'currency' => $roomType->currency,
            'amenities' => $roomType->amenities,
            'status' => $roomType->status,
        ];
    }
}
