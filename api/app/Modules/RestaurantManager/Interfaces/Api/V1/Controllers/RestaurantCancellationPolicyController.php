<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantCancellationPolicyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-603 (#6208) — Politique d'annulation configurable par branche.
 *
 * `PUT /restaurant/branches/{branch}/cancellation-policy` : met à jour
 * `cancel_free_hours` (délai de grâce) et `cancel_fee_bps` (pénalité) —
 * tous les calculs de pénalité restent serveur
 * (`RestaurantCancellationPolicyService`).
 */
class RestaurantCancellationPolicyController extends Controller
{
    public function update(UpdateRestaurantCancellationPolicyRequest $request, RestaurantBranch $restaurantBranch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantBranch->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantBranch)) {
            abort(403);
        }

        $restaurantBranch->cancel_free_hours = $request->validated('cancel_free_hours');
        $restaurantBranch->cancel_fee_bps = $request->validated('cancel_fee_bps');
        $restaurantBranch->save();

        return response()->json([
            'data' => [
                'branch_id' => $restaurantBranch->id,
                'cancel_free_hours' => $restaurantBranch->cancel_free_hours,
                'cancel_fee_bps' => $restaurantBranch->cancel_fee_bps,
            ],
        ]);
    }
}
