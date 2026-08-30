<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\PurchaseOrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrderItem;
use App\Modules\RestaurantManager\Infrastructure\Services\ReceivingService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-502 (#6201) — Transitions d'un bon de commande fournisseur.
 *
 * send    : draft → sent (envoi au fournisseur) ;
 * receive : sent → received — réception INTÉGRALE des lignes commandées :
 * génère une réception (reference RCV-), les entrées de stock (mouvements
 * `receiving`) et le recalcul du coût moyen pondéré (ReceivingService).
 */
final class PurchaseOrderAction
{
    public function __construct(private readonly ReceivingService $receiving)
    {
    }

    public function send(Employee $actor, RestaurantPurchaseOrder $po): RestaurantPurchaseOrder
    {
        if ($po->company_id !== $actor->company_id) {
            throw new RuntimeException('Purchase order does not belong to tenant.');
        }

        if ($po->status !== PurchaseOrderStatus::DRAFT) {
            abort(409, sprintf('Only a draft purchase order can be sent (current status "%s").', $po->status->value));
        }

        $po->forceFill(['status' => PurchaseOrderStatus::SENT->value])->save();

        return $po;
    }

    public function receive(Employee $actor, RestaurantPurchaseOrder $po): RestaurantPurchaseOrder
    {
        if ($po->company_id !== $actor->company_id) {
            throw new RuntimeException('Purchase order does not belong to tenant.');
        }

        if ($po->status !== PurchaseOrderStatus::SENT) {
            abort(409, sprintf('Only a sent purchase order can be received (current status "%s").', $po->status->value));
        }

        $lines = $po->items()
            ->get(['ingredient_id', 'quantity', 'unit_price_minor'])
            ->map(fn (RestaurantPurchaseOrderItem $item) => [
                'ingredient_id' => (int) $item->ingredient_id,
                'quantity' => (float) $item->quantity,
                'unit_price_minor' => (int) $item->unit_price_minor,
            ])
            ->all();

        $this->receiving->receive(
            companyId: $po->company_id,
            branchId: $po->branch_id,
            lines: $lines,
            purchaseOrderId: $po->id,
            supplierId: $po->supplier_id,
            userId: $actor->id,
        );

        DB::table('restaurant_purchase_orders')
            ->where('id', $po->id)
            ->where('company_id', $po->company_id)
            ->update([
                'status' => PurchaseOrderStatus::RECEIVED->value,
                'received_at' => now(),
            ]);

        $po->refresh();

        return $po;
    }

    public function cancel(Employee $actor, RestaurantPurchaseOrder $po): RestaurantPurchaseOrder
    {
        if ($po->company_id !== $actor->company_id) {
            throw new RuntimeException('Purchase order does not belong to tenant.');
        }

        if (! in_array($po->status, [PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::SENT], true)) {
            abort(409, 'Only a draft or sent purchase order can be cancelled.');
        }

        $po->forceFill(['status' => PurchaseOrderStatus::CANCELLED->value])->save();

        return $po;
    }
}
