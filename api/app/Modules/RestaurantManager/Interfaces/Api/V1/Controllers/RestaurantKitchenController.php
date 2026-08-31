<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\TransitionOrderAction;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantKitchenOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-410 (#6197) — File cuisine : liste des commandes par branche et
 * transitions start/ready.
 *
 * Périmètre « le cuisinier ne voit que les commandes de sa branche » :
 * `branch_id` est OBLIGATOIRE et résolu tenant-scope — la file ne contient
 * que les commandes de cette branche (une branche d'un autre tenant → 404).
 * Rôles : cuisinier, manager de salle ou supérieur (restaurant.kitchen /
 * restaurant.manager / restaurant.manage).
 */
class RestaurantKitchenController extends Controller
{
    public function __construct(private readonly TransitionOrderAction $transitionAction)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $this->isKitchenRole($actor)) {
            abort(403);
        }

        $branchId = $request->query('branch_id');

        if (! is_numeric($branchId)) {
            abort(422, 'branch_id is required to list the kitchen queue.');
        }

        $branch = RestaurantBranch::query()
            ->where('company_id', $actor->company_id)
            ->find((int) $branchId);

        if (! $branch instanceof RestaurantBranch) {
            abort(404);
        }

        $orders = RestaurantOrder::query()
            ->with(['items.product'])
            ->where('company_id', $actor->company_id)
            ->where('branch_id', $branch->id)
            ->whereIn('status', [OrderStatus::IN_PREPARATION->value, OrderStatus::READY->value])
            ->orderBy('created_at')
            ->get();

        return RestaurantKitchenOrderResource::collection($orders)->response();
    }

    public function start(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $this->isKitchenRole($actor)) {
            abort(403);
        }

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        $order = $this->transitionAction->transition($actor, $restaurantOrder, OrderStatus::IN_PREPARATION);

        return (new RestaurantKitchenOrderResource($order->load('items.product')))->response();
    }

    public function ready(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $this->isKitchenRole($actor)) {
            abort(403);
        }

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        $order = $this->transitionAction->transition($actor, $restaurantOrder, OrderStatus::READY);

        return (new RestaurantKitchenOrderResource($order->load('items.product')))->response();
    }

    private function isKitchenRole(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'kitchen');
    }
}
