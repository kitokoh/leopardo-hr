<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\AddOrderItemAction;
use App\Modules\RestaurantManager\Application\Actions\CancelOrderItemAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantOrderItemRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantOrderItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-403 (#6190) — Articles de commande : ajout et annulation.
 *
 * Ajout : produit du référentiel tenant (prix serveur, TVA serveur), totaux
 * recalculés immédiatement. Annulation : ligne `active → cancelled` (trace
 * conservée) + recalcul. 404 sûr cross-tenant au niveau contrôleur.
 */
class RestaurantOrderItemController extends Controller
{
    public function __construct(
        private readonly AddOrderItemAction $addAction,
        private readonly CancelOrderItemAction $cancelAction,
    ) {
    }

    public function store(StoreRestaurantOrderItemRequest $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantOrder)) {
            abort(403);
        }

        $item = $this->addAction->add($actor, $restaurantOrder, $request->validated());

        return (new RestaurantOrderItemResource($item))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, RestaurantOrder $restaurantOrder, RestaurantOrderItem $restaurantOrderItem): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id || $actor->company_id !== $restaurantOrderItem->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantOrder)) {
            abort(403);
        }

        $item = $this->cancelAction->cancel($actor, $restaurantOrder, $restaurantOrderItem);

        return (new RestaurantOrderItemResource($item))->response();
    }
}
