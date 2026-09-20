<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranchStaff;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantBranchStaffService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantBranchStaffRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantBranchStaffRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantBranchStaffResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #7909 — Affectations d'employés aux succursales restaurant.
 *
 * Sous-ressource de `{restaurantBranch}` : toute résolution d'une branche
 * (ou d'une affectation) d'un autre tenant renvoie 404 (jamais 403, qui
 * révélerait l'existence de la ressource) ; le contrôle `company_id`
 * précède systématiquement l'appel à `RestaurantBranchStaffPolicy`.
 * La validation cross-tenant de l'employé (422) et l'unicité (409) sont
 * portées par `RestaurantBranchStaffService::assign()`.
 */
class RestaurantBranchStaffController extends Controller
{
    public function __construct(private readonly RestaurantBranchStaffService $staffService) {}

    /**
     * Liste paginée des affectations d'une succursale (employé embarqué).
     */
    public function index(Request $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        if ($actor->cannot('viewAny', RestaurantBranchStaff::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $assignments = RestaurantBranchStaff::query()
            ->with('employee')
            ->where('branch_id', $restaurantBranch->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return RestaurantBranchStaffResource::collection($assignments)->response();
    }

    public function store(StoreRestaurantBranchStaffRequest $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', [RestaurantBranchStaff::class, $restaurantBranch->id])) {
            abort(403);
        }

        $assignment = $this->staffService->assign($restaurantBranch, $actor, $request->validated());
        $assignment->load('employee');

        return (new RestaurantBranchStaffResource($assignment))->response()->setStatusCode(201);
    }

    public function update(
        UpdateRestaurantBranchStaffRequest $request,
        RestaurantBranch $restaurantBranch,
        RestaurantBranchStaff $restaurantBranchStaff
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id
            || $restaurantBranchStaff->branch_id !== $restaurantBranch->id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantBranchStaff)) {
            abort(403);
        }

        $restaurantBranchStaff->update($request->validated());
        $restaurantBranchStaff->load('employee');

        return (new RestaurantBranchStaffResource($restaurantBranchStaff))->response();
    }

    /**
     * Retrait d'une affectation — soft delete (historique conservé, une
     * ré-affectation restaure la ligne, cf. RestaurantBranchStaffService).
     */
    public function destroy(
        Request $request,
        RestaurantBranch $restaurantBranch,
        RestaurantBranchStaff $restaurantBranchStaff
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id
            || $restaurantBranchStaff->branch_id !== $restaurantBranch->id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantBranchStaff)) {
            abort(403);
        }

        $restaurantBranchStaff->delete();

        return new JsonResponse(null, 204);
    }
}
