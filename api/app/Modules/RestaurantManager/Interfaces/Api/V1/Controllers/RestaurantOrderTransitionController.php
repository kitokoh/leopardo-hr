<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\TransitionOrderAction;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-404 (#6191) — Transitions d'état d'une commande
 * (submit/confirm/serve/cancel).
 *
 * submit : draft → open (envoi en cuisine, événement order.created.v1)
 * confirm : open → in_preparation (prise en cuisine côté caisse)
 * serve   : ready → served (service en salle)
 * cancel  : draft|open → cancelled
 *
 * Toute transition hors workflow est refusée (409). 404 sûr cross-tenant.
 */
class RestaurantOrderTransitionController extends Controller
{
    public function __construct(private readonly TransitionOrderAction $transitionAction)
    {
    }

    public function submit(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        return $this->transition($request, $restaurantOrder, OrderStatus::OPEN);
    }

    public function confirm(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        return $this->transition($request, $restaurantOrder, OrderStatus::IN_PREPARATION);
    }

    public function serve(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        return $this->transition($request, $restaurantOrder, OrderStatus::SERVED);
    }

    public function cancel(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        return $this->transition($request, $restaurantOrder, OrderStatus::CANCELLED);
    }

    private function transition(Request $request, RestaurantOrder $order, OrderStatus $target): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $order->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $order)) {
            abort(403);
        }

        $updated = $this->transitionAction->transition($actor, $order, $target);

        return (new RestaurantOrderResource($updated))->response();
    }
}
