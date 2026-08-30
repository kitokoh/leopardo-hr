<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Services\BillCalculator;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantBillResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-405 (#6192) — Addition d'une commande (calcul serveur).
 *
 * `GET /restaurant/orders/{order}/bill?promotion_code=…` recalcule TOUS les
 * totaux côté serveur (sous-total, TVA par taux, remise promo bornée,
 * total en minor units) — aucun montant n'est accepté du client. Les totaux
 * sont persistés sur la commande (la caisse, le paiement et les rapports
 * lisent toujours la version serveur) et le compteur d'utilisations de la
 * promotion est incrémenté dans la même transaction.
 */
class RestaurantBillController extends Controller
{
    public function __construct(private readonly BillCalculator $calculator)
    {
    }

    public function show(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $restaurantOrder)) {
            abort(403);
        }

        $restaurantOrder->load('items');

        $code = $request->query('promotion_code');
        $code = is_string($code) ? $code : null;

        $totals = $this->calculator->calculateWithPromotion($restaurantOrder, $code);

        if (($totals['promotion'] ?? null) !== null) {
            DB::transaction(function () use ($restaurantOrder, $totals): void {
                /** @var \App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion $promotion */
                $promotion = $totals['promotion'];
                $promotion->increment('used_count');

                $restaurantOrder->forceFill([
                    'subtotal_minor' => $totals['subtotal_minor'],
                    'tax_minor' => $totals['tax_minor'],
                    'discount_minor' => $totals['discount_minor'],
                    'total_minor' => $totals['total_minor'],
                ])->save();
            });
        }

        return (new RestaurantBillResource($restaurantOrder, $totals))->response();
    }
}
