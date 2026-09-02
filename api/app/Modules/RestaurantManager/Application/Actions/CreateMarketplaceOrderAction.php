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
use App\Modules\RestaurantManager\Domain\ValueObjects\MarketplaceInboundOrder;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-806 (#6227) — Création d'une commande marketplace (webhook entrant).
 *
 * « Commande marketplace → même workflow interne » (critère d'acceptation) :
 * la commande est créée comme une commande interne `delivery_app` (mêmes
 * invariants que le POS : prix serveur, TVA serveur, BillCalculator, machine
 * à états) puis l'événement `restaurant.order.created.v1` est publié.
 *
 * Rapprochement produit : par `code` produit (unique par tenant) — le code
 * interne est utilisé comme identifiant externe lors de la configuration du
 * menu côté marketplace. Aucun montant du payload n'est accepté tel quel.
 */
final class CreateMarketplaceOrderAction
{
    public function __construct(
        private readonly BillCalculator $calculator,
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
    }

    public function create(string $companyId, MarketplaceInboundOrder $inbound): RestaurantOrder
    {
        /** @var RestaurantBranch $branch */
        $branch = $this->resolveBranch($companyId, $inbound->branchCode);

        $order = DB::transaction(function () use ($companyId, $branch, $inbound): RestaurantOrder {
            /** @var RestaurantOrder $order */
            $order = RestaurantOrder::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'pos_session_id' => null,
                'order_type' => 'delivery',
                'table_id' => null,
                'zone_id' => null,
                'covers' => null,
                'customer_contact_id' => null,
                'status' => OrderStatus::DRAFT->value,
                'currency' => $branch->currency ?: 'DZD',
                'source' => OrderSource::DELIVERY_APP->value,
                'note_redacted' => $inbound->note !== null ? mb_substr($inbound->note, 0, 1000) : null,
                'idempotency_key' => null,
                'version' => 1,
            ]);

            $lineIndex = 0;
            foreach ($inbound->items as $item) {
                $product = RestaurantProduct::query()
                    ->where('company_id', $companyId)
                    ->where('code', mb_strtoupper($item->externalProductId))
                    ->where('status', RestaurantRecordStatus::ACTIVE->value)
                    ->where('is_available', true)
                    ->first();

                if (! $product instanceof RestaurantProduct) {
                    abort(422, __('restaurant.marketplace.product_not_found', ['code' => $item->externalProductId]));
                }

                if ($product->branch_id !== null && $product->branch_id !== $branch->id) {
                    abort(422, __('restaurant.marketplace.product_not_served', ['code' => $item->externalProductId]));
                }

                $quantity = $item->quantity;
                if ($quantity <= 0) {
                    abort(422, __('restaurant.marketplace.quantity_invalid'));
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
                abort(422, __('restaurant.marketplace.empty_order'));
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

        $this->outbox->publish($companyId, 'restaurant.order.created.v1', [
            'order_id' => $order->id,
            'reference' => $order->reference,
            'source' => OrderSource::DELIVERY_APP->value,
            'total_minor' => $order->total_minor,
            'currency' => $order->currency,
            'branch_id' => $order->branch_id,
        ]);

        return $order;
    }

    /**
     * Branche cible : `branchCode` si fourni et présent, sinon la première
     * branche active du tenant (fail-closed si aucun référentiel).
     */
    private function resolveBranch(string $companyId, ?string $branchCode): RestaurantBranch
    {
        $query = RestaurantBranch::query()->where('company_id', $companyId);

        if ($branchCode !== null && $branchCode !== '') {
            $branch = (clone $query)->where('code', $branchCode)->first();

            if ($branch instanceof RestaurantBranch) {
                return $branch;
            }
        }

        /** @var RestaurantBranch|null $branch */
        $branch = $query->orderBy('id')->first();

        if (! $branch instanceof RestaurantBranch) {
            abort(422, __('restaurant.marketplace.no_branch'));
        }

        return $branch;
    }

    /**
     * TVA serveur : line_total × rate_bps / 10 000 (miroir AddOrderItemAction).
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
