<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\PurchaseOrderAction;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPurchaseOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantPurchaseOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-502 (#6201) — Bons de commande fournisseurs (CRUD + transitions).
 *
 * `POST /purchase-orders/{po}/send` : draft → sent.
 * `POST /purchase-orders/{po}/receive` : sent → received — réception
 * intégrale → entrées de stock + coût moyen pondéré (RESTO-503).
 * `POST /purchase-orders/{po}/cancel` : draft|sent → cancelled.
 * Le total est TOUJOURS recalculé serveur (Σ lignes) ; 404 sûr cross-tenant.
 */
class RestaurantPurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderAction $poAction)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantPurchaseOrder::class)) {
            abort(403);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $orders = RestaurantPurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->when($request->has('branch_id'), fn ($query) => $query->where('branch_id', (int) $request->query('branch_id')))
            ->when($request->has('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return RestaurantPurchaseOrderResource::collection($orders)->response();
    }

    public function store(StoreRestaurantPurchaseOrderRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', RestaurantPurchaseOrder::class)) {
            abort(403);
        }

        $order = RestaurantPurchaseOrder::query()->create($request->validated());

        return (new RestaurantPurchaseOrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPurchaseOrder->company_id) {
            abort(404);
        }

        $restaurantPurchaseOrder->load(['supplier', 'items']);

        return (new RestaurantPurchaseOrderResource($restaurantPurchaseOrder))->response();
    }

    public function update(UpdateRestaurantPurchaseOrderRequest $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPurchaseOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantPurchaseOrder)) {
            abort(403);
        }

        if ($restaurantPurchaseOrder->status->value !== 'draft') {
            abort(409, 'Only a draft purchase order can be edited.');
        }

        $restaurantPurchaseOrder->update($request->validated());

        return (new RestaurantPurchaseOrderResource($restaurantPurchaseOrder))->response();
    }

    public function destroy(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPurchaseOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $restaurantPurchaseOrder)) {
            abort(403);
        }

        if ($restaurantPurchaseOrder->status->value !== 'draft') {
            abort(409, 'Only a draft purchase order can be deleted.');
        }

        $restaurantPurchaseOrder->delete();

        return new JsonResponse(null, 204);
    }

    public function send(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        return $this->transition($request, $restaurantPurchaseOrder, 'send');
    }

    public function receive(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        return $this->transition($request, $restaurantPurchaseOrder, 'receive');
    }

    public function cancel(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        return $this->transition($request, $restaurantPurchaseOrder, 'cancel');
    }

    private function transition(Request $request, RestaurantPurchaseOrder $po, string $action): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $po->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $po)) {
            abort(403);
        }

        $po = match ($action) {
            'send' => $this->poAction->send($actor, $po),
            'receive' => $this->poAction->receive($actor, $po),
            default => $this->poAction->cancel($actor, $po),
        };

        return (new RestaurantPurchaseOrderResource($po->load('items')))->response();
    }
}
