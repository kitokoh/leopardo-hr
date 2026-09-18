<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantCogsService;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-506 (#6205) — COGS serveur à la clôture de caisse.
 *
 * `GET /restaurant/pos-sessions/{session}/cogs` : calcul pur et idempotent
 * du coût des marchandises vendues (quantités × composition × coût moyen).
 */
class RestaurantCogsController extends Controller
{
    use ChecksRestaurantBranchAccess;

    public function __construct(
        private readonly RestaurantCogsService $cogs,
    ) {}

    public function show(Request $request, RestaurantPosSession $restaurantPosSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPosSession->company_id) {
            abort(404);
        }

        // #7599 — lecture ressource-scopée : un employé sans assignation ne
        // lit plus les données métier dès que le scoping est actif.
        if ($actor->cannot('view', $restaurantPosSession)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        // #7599 (trou n°4 de l'épique #7597) : le COGS est une donnée de
        // gestion sensible — niveau `manage` sur la succursale de la session.
        if (! $this->canManageBranchResource($actor, $restaurantPosSession->branch_id)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        return response()->json($this->cogs->calculateForPosSession($restaurantPosSession));
    }
}
