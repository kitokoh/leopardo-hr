<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantCogsService;
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
    public function __construct(
        private readonly RestaurantCogsService $cogs,
    ) {
    }

    public function show(Request $request, RestaurantPosSession $restaurantPosSession): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPosSession->company_id) {
            abort(404);
        }

        return response()->json($this->cogs->calculateForPosSession($restaurantPosSession));
    }
}
