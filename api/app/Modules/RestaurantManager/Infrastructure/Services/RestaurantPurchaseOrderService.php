<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\PurchaseOrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\StockMovementReason;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-502 (#6201) — Bons de commande fournisseurs : transitions métier.
 *
 * `send` : draft → sent (immutable) ; `receive` : génère la réception et les
 * mouvements de stock via `RestaurantStockMovementService` (raison
 * `receiving`, référence du bon de commande), puis passe le bon en
 * `received` (réception complète) ou reste `sent` si réception partielle.
 *
 * Le total est toujours recalculé serveur à partir des lignes (jamais
 * accepté depuis le client) — les montants restent en minor units.
 */
final class RestaurantPurchaseOrderService
{
    public function __construct(
        private readonly RestaurantStockMovementService $stockMovements,
    ) {
    }

    /**
     * Recalcule le total du bon (minor units) depuis ses lignes.
     */
    public function recomputeTotal(RestaurantPurchaseOrder $order): int
    {
        $total = 0;

        foreach ($order->items as $item) {
            $total += (int) $item->line_total_minor;
        }

        $order->total_minor = $total;
        $order->save();

        return $total;
    }

    /**
     * Crée (ou complète) les lignes d'un bon et recalcule le total.
     *
     * @param  array<int, array{ingredient_id: int, quantity: string, unit_price_minor: int}>  $items
     */
    public function syncItems(RestaurantPurchaseOrder $order, array $items): void
    {
        foreach ($items as $line) {
            $quantity = (string) $line['quantity'];
            $unitPrice = (int) $line['unit_price_minor'];
            $lineTotal = (int) round((float) $quantity * $unitPrice);

            RestaurantPurchaseOrderItem::query()->create([
                'company_id' => $order->company_id,
                'purchase_order_id' => $order->id,
                'ingredient_id' => (int) $line['ingredient_id'],
                'quantity' => $quantity,
                'unit_price_minor' => $unitPrice,
                'line_total_minor' => $lineTotal,
            ]);
        }

        $this->recomputeTotal($order);
    }

    /**
     * Passe le bon en `sent` (validation fournisseur). Un bon reçu ou annulé
     * est immutable.
     */
    public function send(RestaurantPurchaseOrder $order, Employee $actor): RestaurantPurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::DRAFT) {
            throw new RuntimeException('Seul un bon de commande en brouillon peut être envoyé.');
        }

        $order->status = PurchaseOrderStatus::SENT;
        $order->save();

        return $order;
    }

    /**
     * Réceptionne tout ou partie des lignes : crée les mouvements de stock
     * (raison `receiving`, référence PO) et bascule le bon en `received`
     * quand toutes les lignes sont intégralement reçues.
     *
     * @param  array<int, array{ingredient_id: int, quantity: string, unit_price_minor: int}>  $items  quantités reçues (optionnel : tout le bon)
     * @return array{order: RestaurantPurchaseOrder, received_ingredient_ids: array<int>}
     */
    public function receive(RestaurantPurchaseOrder $order, Employee $actor, array $items): array
    {
        if ($order->status !== PurchaseOrderStatus::SENT) {
            throw new RuntimeException('Seul un bon de commande envoyé peut être réceptionné.');
        }

        $receivedIngredientIds = [];

        DB::transaction(function () use ($order, $actor, $items, &$receivedIngredientIds): void {
            foreach ($items as $line) {
                $ingredientId = (int) $line['ingredient_id'];
                $quantity = (string) $line['quantity'];

                $this->stockMovements->apply(
                    companyId: $order->company_id,
                    branchId: $order->branch_id,
                    ingredientId: $ingredientId,
                    quantityDelta: $quantity,
                    reason: StockMovementReason::RECEIVING,
                    referenceType: 'purchase_order',
                    referenceId: $order->id,
                    noteRedacted: 'Réception PO '.$order->reference,
                    userId: (int) $actor->id,
                    unitPriceMinor: (int) $line['unit_price_minor'],
                );

                $receivedIngredientIds[] = $ingredientId;
            }

            $order->received_at = now();
            $order->status = PurchaseOrderStatus::RECEIVED;
            $order->save();
        });

        return ['order' => $order, 'received_ingredient_ids' => $receivedIngredientIds];
    }
}
