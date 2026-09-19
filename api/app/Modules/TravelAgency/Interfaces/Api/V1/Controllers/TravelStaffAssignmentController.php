<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Enums\TravelStaffAssignmentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelStaffAssignment;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelStaffAssignmentRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelStaffAssignmentRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelStaffAssignmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #7638 (TRAVEL-STAFF) — CRUD des affectations d'équipage.
 *
 * Même schéma que `TravelOfficeController` : 404 sûr cross-tenant sur la
 * ressource elle-même, jamais 403. La révocation est un acte métier
 * (POST, convention #4930) qui conserve la ligne pour l'historique.
 */
class TravelStaffAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelStaffAssignment::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $assignments = TravelStaffAssignment::query()
            ->when($request->filled('employee_id'), fn ($query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('office_id'), fn ($query) => $query->where('office_id', $request->integer('office_id')))
            ->when($request->filled('trip_id'), fn ($query) => $query->where('trip_id', $request->integer('trip_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->when($request->filled('role'), fn ($query) => $query->where('role', (string) $request->query('role')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return TravelStaffAssignmentResource::collection($assignments)->response();
    }

    public function store(StoreTravelStaffAssignmentRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelStaffAssignment::class)) {
            abort(403);
        }

        $validated = $request->validated();

        // Idempotence métier : pas de doublon ACTIF employé×rôle×scope.
        $duplicate = TravelStaffAssignment::query()
            ->active()
            ->where('employee_id', $validated['employee_id'])
            ->where('role', $validated['role'])
            ->when(isset($validated['office_id']), fn ($query) => $query->where('office_id', $validated['office_id']))
            ->when(isset($validated['trip_id']), fn ($query) => $query->where('trip_id', $validated['trip_id']))
            ->exists();

        if ($duplicate) {
            abort(409, 'Cet employé est déjà affecté à ce rôle sur ce scope.');
        }

        $assignment = TravelStaffAssignment::query()->create($validated);

        return (new TravelStaffAssignmentResource($assignment))->response()->setStatusCode(201);
    }

    public function show(Request $request, TravelStaffAssignment $travelStaffAssignment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelStaffAssignment->company_id) {
            abort(404);
        }

        return (new TravelStaffAssignmentResource($travelStaffAssignment))->response();
    }

    public function update(UpdateTravelStaffAssignmentRequest $request, TravelStaffAssignment $travelStaffAssignment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelStaffAssignment->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelStaffAssignment)) {
            abort(403);
        }

        $travelStaffAssignment->update($request->validated());

        return (new TravelStaffAssignmentResource($travelStaffAssignment))->response();
    }

    /**
     * Révocation (acte métier → POST, #4930) : la ligne reste pour
     * l'historique des manifestes ; idempotente sur une ligne déjà révoquée.
     */
    public function revoke(Request $request, TravelStaffAssignment $travelStaffAssignment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelStaffAssignment->company_id) {
            abort(404);
        }

        if ($actor->cannot('revoke', $travelStaffAssignment)) {
            abort(403);
        }

        if ($travelStaffAssignment->isActive()) {
            $travelStaffAssignment->update([
                'status' => TravelStaffAssignmentStatus::REVOKED->value,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
            ]);
        }

        return (new TravelStaffAssignmentResource($travelStaffAssignment))->response();
    }

    public function destroy(Request $request, TravelStaffAssignment $travelStaffAssignment): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelStaffAssignment->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelStaffAssignment)) {
            abort(403);
        }

        $travelStaffAssignment->delete();

        return new JsonResponse(null, 204);
    }
}
