<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\PurchaseOrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrderItem;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPurchaseOrderItemRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Resources\RestaurantPurchaseOrderItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-502 (#6201) — Lignes d'un bon de commande fournisseur.
 *
 * Lignes modifiables uniquement sur un PO `draft` ; le total du PO est
 * recalculé serveur après chaque ajout/suppression. 404 sûr cross-tenant.
 */
class RestaurantPurchaseOrderItemController extends Controller
{
    public function store(StoreRestaurantPurchaseOrderItemRequest $request, RestaurantPurchaseOrder $restaurantPurchaseOrder): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPurchaseOrder->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantPurchaseOrder)) {
            abort(403);
        }

        if ($restaurantPurchaseOrder->status !== PurchaseOrderStatus::DRAFT) {
            abort(409, 'Items can only be added to a draft purchase order.');
        }

        $data = $request->validated();
        $lineTotal = (int) round((float) $data['quantity'] * (int) $data['unit_price_minor']);

        $item = RestaurantPurchaseOrderItem::query()->create([
            'company_id' => $restaurantPurchaseOrder->company_id,
            'purchase_order_id' => $restaurantPurchaseOrder->id,
            'ingredient_id' => $data['ingredient_id'],
            'quantity' => $data['quantity'],
            'unit_price_minor' => $data['unit_price_minor'],
            'line_total_minor' => $lineTotal,
        ]);

        $this->recalculateTotal($restaurantPurchaseOrder);

        return (new RestaurantPurchaseOrderItemResource($item))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, RestaurantPurchaseOrder $restaurantPurchaseOrder, RestaurantPurchaseOrderItem $restaurantPurchaseOrderItem): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $restaurantPurchaseOrder->company_id || $actor->company_id !== $restaurantPurchaseOrderItem->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $restaurantPurchaseOrder)) {
            abort(403);
        }

        if ($restaurantPurchaseOrder->status !== PurchaseOrderStatus::DRAFT) {
            abort(409, 'Items can only be removed from a draft purchase order.');
        }

        if ($restaurantPurchaseOrderItem->purchase_order_id !== $restaurantPurchaseOrder->id) {
            abort(422, 'Item does not belong to this purchase order.');
        }

        $restaurantPurchaseOrderItem->delete();
        $this->recalculateTotal($restaurantPurchaseOrder);

        return new JsonResponse(null, 204);
    }

    private function recalculateTotal(RestaurantPurchaseOrder $po): void
    {
        $total = (int) RestaurantPurchaseOrderItem::query()
            ->where('company_id', $po->company_id)
            ->where('purchase_order_id', $po->id)
            ->sum('line_total_minor');

        $po->forceFill(['total_minor' => $total])->save();
    }
}
