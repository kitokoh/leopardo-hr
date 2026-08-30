<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\PayOrderAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\PayRestaurantOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantOrderPaymentResource;
use Illuminate\Http\JsonResponse;

/**
 * RESTO-407 (#6194) — Encaissement d'une commande.
 *
 * `POST /restaurant/orders/{order}/pay` : montant vérifié serveur, rejeu
 * idempotent, double paiement impossible (409). Le callback de confirmation
 * mobile money (signé HMAC) est traité par RestaurantPaymentCallbackController
 * (route publique hors groupe auth). 404 sûr cross-tenant.
 */
class RestaurantPaymentController extends Controller
{
    public function __construct(private readonly PayOrderAction $payAction)
    {
    }

    public function pay(PayRestaurantOrderRequest $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', RestaurantOrderPayment::class)) {
            abort(403);
        }

        $payment = $this->payAction->pay($actor, $restaurantOrder, $request->validated());

        return (new RestaurantOrderPaymentResource($payment))->response()->setStatusCode(201);
    }
}
