<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\ApplyKitchenActionAction;
use App\Modules\RestaurantManager\Application\Actions\TransitionOrderAction;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantKitchenOrderResource;
use App\Modules\RestaurantManager\Policies\Concerns\ChecksRestaurantBranchAccess;
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
    use ChecksRestaurantBranchAccess;

    public function __construct(
        private readonly TransitionOrderAction $transitionAction,
        private readonly ApplyKitchenActionAction $applyKitchenAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

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

        // #7599 — la file cuisine est un geste opérationnel de LA succursale.
        if (! $this->canOperateBranchResource($actor, $branch->id)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
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

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        if (! $this->canOperateBranchResource($actor, $restaurantOrder->branch_id)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        $order = $this->transitionAction->execute($actor, $restaurantOrder, OrderStatus::IN_PREPARATION);

        return (new RestaurantKitchenOrderResource($order->load('items.product')))->response();
    }

    public function ready(Request $request, RestaurantOrder $restaurantOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantOrder->company_id) {
            abort(404);
        }

        if (! $this->canOperateBranchResource($actor, $restaurantOrder->branch_id)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        $order = $this->transitionAction->execute($actor, $restaurantOrder, OrderStatus::READY);

        return (new RestaurantKitchenOrderResource($order->load('items.product')))->response();
    }

    /**
     * #8448 — geste cuisine venu du KDS Edge hors ligne, IDEMPOTENT.
     *
     * Le client rejoue ses gestes au retour du réseau ; un rejeu ne doit jamais
     * faire avancer la commande deux fois. L'idempotence est portée par la
     * contrainte d'unicité de `restaurant_kitchen_actions` (voir
     * `ApplyKitchenActionAction`), pas par un `SELECT` préalable qui laisserait
     * passer deux rejeux concurrents.
     *
     * Un rejeu répond **200** et non 409 : c'est le fonctionnement normal d'un
     * client hors ligne, pas une erreur à afficher au cuisinier.
     */
    public function applyAction(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'client_action_id' => ['required', 'string', 'max:64'],
            'order_id' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:start,ready,serve'],
        ]);

        $order = RestaurantOrder::query()
            ->where('company_id', $actor->company_id)
            ->find((int) $validated['order_id']);

        if (! $order instanceof RestaurantOrder) {
            abort(404);
        }

        if (! $this->canOperateBranchResource($actor, $order->branch_id)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        $result = $this->applyKitchenAction->execute(
            $actor,
            $order,
            (string) $validated['client_action_id'],
            (string) $validated['action'],
        );

        return (new RestaurantKitchenOrderResource($result['order']->load('items.product')))
            ->additional(['meta' => ['replayed' => $result['replayed']]])
            ->response();
    }
}
