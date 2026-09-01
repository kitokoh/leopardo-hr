<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\CreateDeliveryAction;
use App\Modules\RestaurantManager\Application\Actions\TransitionDeliveryAction;
use App\Modules\RestaurantManager\Domain\Enums\DeliveryStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDelivery;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantDeliveryRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\TransitionRestaurantDeliveryRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantDeliveryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-605 (#6210) — Cycle de livraison RestaurantManager.
 *
 * - POST /deliveries : crée la livraison d'une commande à livrer (frais
 *   recalculé serveur depuis la zone, idempotente par commande) ;
 * - POST /deliveries/{delivery}/assign|out-for-delivery|deliver|cancel :
 *   transitions validées par DeliveryStateMachine.
 */
class RestaurantDeliveryController extends Controller
{
    public function __construct(
        private readonly CreateDeliveryAction $createDelivery,
        private readonly TransitionDeliveryAction $transitionDelivery,
    ) {
    }

    public function store(StoreRestaurantDeliveryRequest $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('create', RestaurantDelivery::class)) {
            abort(403);
        }

        $delivery = $this->createDelivery->create($actor, $restaurantOrder, $request->validated());

        return (new RestaurantDeliveryResource($delivery))->response()->setStatusCode(201);
    }

    public function transition(
        TransitionRestaurantDeliveryRequest $request,
        RestaurantDelivery $restaurantDelivery,
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDelivery->company_id) {
            abort(404);
        }

        if ($actor->cannot('transition', $restaurantDelivery)) {
            abort(403);
        }

        $delivery = $this->transitionDelivery->transition(
            $actor,
            $restaurantDelivery,
            $this->targetStatus($request),
            $request->validated(),
        );

        return (new RestaurantDeliveryResource($delivery))->response();
    }

    private function targetStatus(Request $request): DeliveryStatus
    {
        $action = (string) last(explode('/', $request->path()));

        return match ($action) {
            'assign' => DeliveryStatus::ASSIGNED,
            'out-for-delivery' => DeliveryStatus::OUT_FOR_DELIVERY,
            'deliver' => DeliveryStatus::DELIVERED,
            'cancel' => DeliveryStatus::CANCELLED,
            default => abort(422, 'Unknown delivery action.'),
        };
    }

    public function show(Request $request, RestaurantDelivery $restaurantDelivery): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantDelivery->company_id) {
            abort(404);
        }

        return (new RestaurantDeliveryResource($restaurantDelivery))->response();
    }
}
