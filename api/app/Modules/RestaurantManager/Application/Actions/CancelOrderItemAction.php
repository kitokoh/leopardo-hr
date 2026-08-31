<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Services\BillCalculator;
use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-403 (#6190) — Annulation d'une ligne d'article de commande.
 *
 * La ligne passe `active → cancelled` (trace conservée, jamais supprimée) et
 * les totaux de la commande sont recalculés serveur. Annulation refusée une
 * fois la commande en préparation ou au-delà (409).
 */
final class CancelOrderItemAction
{
    public function __construct(private readonly BillCalculator $calculator)
    {
    }

    public function cancel(Employee $actor, RestaurantOrder $order, RestaurantOrderItem $item): RestaurantOrderItem
    {
        if ($order->company_id !== $actor->company_id || $item->company_id !== $actor->company_id) {
            throw new RuntimeException('Resource does not belong to tenant.');
        }

        if ($item->order_id !== $order->id) {
            abort(422, 'Item does not belong to this order.');
        }

        if (! in_array($order->status, [OrderStatus::DRAFT, OrderStatus::OPEN], true)) {
            abort(409, 'Items can only be cancelled on a draft or open order.');
        }

        if ($item->status !== OrderItemStatus::ACTIVE) {
            abort(409, 'Item is already cancelled.');
        }

        DB::transaction(function () use ($order, $item): void {
            $item->forceFill(['status' => OrderItemStatus::CANCELLED->value])->save();

            $order->load('items');
            $totals = $this->calculator->calculate($order);

            $order->forceFill([
                'subtotal_minor' => $totals['subtotal_minor'],
                'tax_minor' => $totals['tax_minor'],
                'discount_minor' => $totals['discount_minor'],
                'total_minor' => $totals['total_minor'],
            ])->save();
        });

        $item->refresh();

        return $item;
    }
}
