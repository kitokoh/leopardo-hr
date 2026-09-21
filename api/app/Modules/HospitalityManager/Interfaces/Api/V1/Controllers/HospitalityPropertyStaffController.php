<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityPropertyStaff;
use App\Modules\HospitalityManager\Infrastructure\Services\HospitalityPropertyStaffService;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\StoreHospitalityPropertyStaffRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Requests\UpdateHospitalityPropertyStaffRequest;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Resources\HospitalityPropertyStaffResource;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\BoundsPagination;
use App\Modules\HospitalityManager\Interfaces\Api\V1\Traits\ChecksHospitalitySolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de l'équipe par établissement — HOSP-003 (#7945).
 *
 * Affectations staff ↔ établissement : unicité stricte (409 sur doublon
 * actif), ré-affectation = restauration (jamais de doublon physique),
 * retrait = soft delete. Tenant TOUJOURS re-vérifié (404 fail-closed).
 */
class HospitalityPropertyStaffController extends Controller
{
    use BoundsPagination;
    use ChecksHospitalitySolution;

    public function __construct(
        private readonly HospitalityPropertyStaffService $staffService
    ) {}

    public function index(Request $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        // Liste imbriquée : la lecture est scopée à CET établissement (un
        // assigné d'un autre site ne lit pas l'équipe de celui-ci).
        $this->authorize('view', $property);

        $perPage = $this->boundedPerPage($request, 50);

        $assignments = HospitalityPropertyStaff::query()
            ->with('employee')
            ->where('property_id', $property->getKey())
            ->orderByDesc('id')
            ->paginate($perPage);

        return HospitalityPropertyStaffResource::collection($assignments)->response();
    }

    public function store(StoreHospitalityPropertyStaffRequest $request, HospitalityProperty $property): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->authorize('create', [HospitalityPropertyStaff::class, $property->getKey()]);

        $assignment = $this->staffService->assign($property, $actor, $request->validated());
        $assignment->load('employee');

        return (new HospitalityPropertyStaffResource($assignment))->response()->setStatusCode(201);
    }

    public function update(UpdateHospitalityPropertyStaffRequest $request, HospitalityProperty $property, HospitalityPropertyStaff $assignment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->assertSameTenant($assignment, $actor->company_id);

        // L'affectation doit appartenir à l'établissement de la route.
        abort_if($assignment->getAttribute('property_id') !== $property->getKey(), 404);

        $this->authorize('update', $assignment);

        $assignment->update($request->validated());
        $assignment->load('employee');

        return (new HospitalityPropertyStaffResource($assignment->refresh()))->response();
    }

    public function destroy(Request $request, HospitalityProperty $property, HospitalityPropertyStaff $assignment): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($property, $actor->company_id);
        $this->assertSameTenant($assignment, $actor->company_id);

        abort_if($assignment->getAttribute('property_id') !== $property->getKey(), 404);

        $this->authorize('delete', $assignment);

        $assignment->delete();

        return response()->json(null, 204);
    }
}
