<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\RefundOrderAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantRefund;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantRefundRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantRefundResource;
use Illuminate\Http\JsonResponse;

/**
 * RESTO-408 (#6195) — Remboursements de commande (réservé `restaurant.manage`).
 *
 * `POST /restaurant/orders/{order}/refund` : montant borné au restant
 * remboursable, idempotent, événement `restaurant.payment.refunded.v1`.
 * 404 sûr cross-tenant.
 */
class RestaurantRefundController extends Controller
{
    public function __construct(private readonly RefundOrderAction $refundAction)
    {
    }

    public function store(StoreRestaurantRefundRequest $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', RestaurantRefund::class)) {
            abort(403);
        }

        $refund = $this->refundAction->refund($actor, $restaurantOrder, $request->validated());

        return (new RestaurantRefundResource($refund))->response()->setStatusCode(201);
    }
}
