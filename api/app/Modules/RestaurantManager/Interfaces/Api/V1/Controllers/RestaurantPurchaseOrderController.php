<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPurchaseOrderService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\ReceiveRestaurantPurchaseOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPurchaseOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\UpdateRestaurantPurchaseOrderRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * RESTO-502 (#6201) — Bons de commande fournisseurs (draft → sent → received).
 *
 * Le total est recalculé serveur (`RestaurantPurchaseOrderService`), les
 * transitions `send`/`receive` passent par le service (réception → mouvements
 * de stock, raison `receiving`). Un bon reçu ou annulé est immutable.
 */
class RestaurantPurchaseOrderController extends Controller
{
    public function __construct(
        private readonly RestaurantPurchaseOrderService $purchaseOrders,
    ) {
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
            ->with('items')
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', (int) $v))
            ->when($request->query('supplier_id'), fn ($q, $v) => $q->where('supplier_id', (int) $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', (string) $v))
            ->orderByDesc('id')
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

        /** @var RestaurantPurchaseOrder $order */
        $order = RestaurantPurchaseOrder::query()->create([
            'company_id' => $actor->company_id,
            'branch_id' => (int) $request->validated('branch_id'),
            'supplier_id' => (int) $request->validated('supplier_id'),
            'expected_at' => $request->validated('expected_at'),
            'currency' => $request->validated('currency', 'DZD'),
        ]);

        $this->purchaseOrders->syncItems($order, $request->validated('items'));

        return (new RestaurantPurchaseOrderResource($order->load('items')))->response()->setStatusCode(201);
    }

    public function show(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPurchaseOrder->company_id) {
            abort(404);
        }

        return (new RestaurantPurchaseOrderResource($restaurantPurchaseOrder->load('items')))->response();
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
            return response()->json(['message' => 'Un bon envoyé ou reçu est immutable.'], 422);
        }

        $restaurantPurchaseOrder->expected_at = $request->validated('expected_at', $restaurantPurchaseOrder->expected_at);
        $restaurantPurchaseOrder->save();

        if ($request->filled('items')) {
            $this->purchaseOrders->syncItems($restaurantPurchaseOrder, $request->validated('items'));
        }

        return (new RestaurantPurchaseOrderResource($restaurantPurchaseOrder->load('items')))->response();
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
            return response()->json(['message' => 'Seul un bon en brouillon peut être supprimé.'], 422);
        }

        $restaurantPurchaseOrder->items()->delete();
        $restaurantPurchaseOrder->delete();

        return new JsonResponse(null, 204);
    }

    public function send(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPurchaseOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantPurchaseOrder)) {
            abort(403);
        }

        try {
            $order = $this->purchaseOrders->send($restaurantPurchaseOrder, $actor);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantPurchaseOrderResource($order->load('items')))->response();
    }

    public function receive(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPurchaseOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantPurchaseOrder)) {
            abort(403);
        }

        try {
            $result = $this->purchaseOrders->receive(
                $restaurantPurchaseOrder,
                $actor,
                $request->validated('items'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return (new RestaurantPurchaseOrderResource($result['order']->load('items')))->response();
    }
}
