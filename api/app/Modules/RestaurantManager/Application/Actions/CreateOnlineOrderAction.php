<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Modules\RestaurantManager\Application\Services\BillCalculator;
use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-805 (#6226) — Création d'une commande en ligne publique.
 *
 * Variante de `CreateOrderAction` SANS acteur authentifié : le tenant est
 * résolu par le jeton de boutique (middleware `restaurant.public.shop`),
 * `currentCompany()` est donc posé — le scope BelongsToCompany s'applique.
 *
 * Invariants (mêmes que le POS) :
 * - aucun montant accepté depuis le client : prix unitaire + TVA serveur,
 *   totaux recalculés par `BillCalculator` (source de vérité unique) ;
 * - produit actif, disponible et servi par la branche de la commande ;
 * - idempotence : une même `idempotency_key` renvoie la commande existante ;
 * - `source = online` (nouveau cas OrderSource), notification cuisine par
 *   événement outbox `restaurant.order.created.v1` (consommé par la file
 *   cuisine / notifications).
 */
final class CreateOnlineOrderAction
{
    public function __construct(
        private readonly BillCalculator $calculator,
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
    }

    /**
     * @param  array{
     *     branch_id: int,
     *     order_type?: string,
     *     items: array<int, array{product_id: int, quantity: float|string}>,
     *     customer_name?: string|null,
     *     customer_phone?: string|null,
     *     note_redacted?: string|null,
     *     idempotency_key?: string|null
     * }  $data
     */
    public function create(array $data): RestaurantOrder
    {
        $company = currentCompany();
        $companyId = $company->id;

        if (isset($data['idempotency_key'])) {
            $existing = RestaurantOrder::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing instanceof RestaurantOrder) {
                return $existing;
            }
        }

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::query()
            ->where('company_id', $companyId)
            ->findOrFail($data['branch_id']);

        $orderType = $data['order_type'] ?? 'takeaway';

        $order = DB::transaction(function () use ($companyId, $branch, $orderType, $data): RestaurantOrder {
            /** @var RestaurantOrder $order */
            $order = RestaurantOrder::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'pos_session_id' => null,
                'order_type' => $orderType,
                'table_id' => null,
                'zone_id' => null,
                'covers' => null,
                'customer_contact_id' => null,
                'status' => OrderStatus::DRAFT->value,
                'currency' => $branch->currency ?: 'DZD',
                'source' => OrderSource::ONLINE->value,
                'note_redacted' => $data['note_redacted'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'version' => 1,
            ]);

            $lineIndex = 0;
            foreach ($data['items'] as $line) {
                $product = RestaurantProduct::query()
                    ->where('company_id', $companyId)
                    ->where('status', RestaurantRecordStatus::ACTIVE->value)
                    ->where('is_available', true)
                    ->find($line['product_id']);

                if (! $product instanceof RestaurantProduct) {
                    abort(422, __('restaurant.public_shop.product_unavailable'));
                }

                if ($product->branch_id !== null && $product->branch_id !== $branch->id) {
                    abort(422, __('restaurant.public_shop.product_not_served'));
                }

                if ($order->currency !== ($product->currency ?: $order->currency)) {
                    abort(422, __('restaurant.public_shop.currency_mismatch'));
                }

                $quantity = (float) $line['quantity'];
                if ($quantity <= 0) {
                    abort(422, __('restaurant.public_shop.quantity_invalid'));
                }

                $lineTotal = (int) round($product->price_minor * $quantity);
                $taxMinor = $this->taxMinorFor($product, $lineTotal, $companyId);

                RestaurantOrderItem::query()->create([
                    'company_id' => $companyId,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'menu_id' => null,
                    'quantity' => $quantity,
                    'unit_price_minor' => $product->price_minor,
                    'line_total_minor' => $lineTotal,
                    'tax_rate_id' => $product->tax_rate_id,
                    'tax_minor' => $taxMinor,
                    'status' => OrderItemStatus::ACTIVE->value,
                    'line_index' => $lineIndex++,
                ]);
            }

            if ($lineIndex === 0) {
                abort(422, __('restaurant.public_shop.empty_order'));
            }

            $order->load('items');
            $totals = $this->calculator->calculate($order);
            $order->forceFill([
                'subtotal_minor' => $totals['subtotal_minor'],
                'tax_minor' => $totals['tax_minor'],
                'discount_minor' => $totals['discount_minor'],
                'total_minor' => $totals['total_minor'],
            ])->save();

            return $order;
        });

        // Notification cuisine/service : commande en ligne arrivée.
        $this->outbox->publish($companyId, 'restaurant.order.created.v1', [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'source' => OrderSource::ONLINE->value,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'branch_id' => $order->branch_id,
        ]);

        return $order;
    }

    /**
     * TVA serveur : line_total × rate_bps / 10 000 (taux du produit) —
     * miroir exact de `AddOrderItemAction::taxMinorFor()`.
     */
    private function taxMinorFor(RestaurantProduct $product, int $lineTotal, string $companyId): ?int
    {
        if ($product->tax_rate_id === null) {
            return null;
        }

        $rate = RestaurantTaxRate::query()
            ->where('company_id', $companyId)
            ->find($product->tax_rate_id);

        if (! $rate instanceof RestaurantTaxRate) {
            return null;
        }

        return (int) round($lineTotal * $rate->rate_bps / 10000);
    }
}
