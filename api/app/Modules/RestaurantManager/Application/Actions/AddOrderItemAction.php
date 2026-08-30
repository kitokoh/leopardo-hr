<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Application\Services\BillCalculator;
use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-403 (#6190) — Ajout d'un article à une commande.
 *
 * Invariants :
 * - le produit doit appartenir au tenant, être actif/disponible et servir la
 *   branche de la commande (`branch_id` null = toutes branches) ;
 * - `quantity` > 0 (décimal, demi-portions autorisées) ; le prix unitaire
 *   vient du référentiel serveur, jamais du client ;
 * - la TVA est calculée serveur (rate_bps du produit) et le total de la
 *   commande est recalculé immédiatement (BillCalculator).
 */
final class AddOrderItemAction
{
    public function __construct(private readonly BillCalculator $calculator)
    {
    }

    /**
     * @param  array{product_id: int, quantity: float|string, menu_id?: int|null}  $data
     */
    public function add(Employee $actor, RestaurantOrder $order, array $data): RestaurantOrderItem
    {
        if ($order->company_id !== $actor->company_id) {
            throw new RuntimeException('Order does not belong to tenant.');
        }

        if (! in_array($order->status, [OrderStatus::DRAFT, OrderStatus::OPEN], true)) {
            abort(409, 'Items can only be added to a draft or open order.');
        }

        $product = RestaurantProduct::query()
            ->where('company_id', $actor->company_id)
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->where('is_available', true)
            ->find($data['product_id']);

        if (! $product instanceof RestaurantProduct) {
            abort(422, 'Product is not available for this tenant.');
        }

        if ($product->branch_id !== null && $product->branch_id !== $order->branch_id) {
            abort(422, 'Product is not served by this branch.');
        }

        if ($order->currency !== ($product->currency ?: $order->currency)) {
            abort(422, 'Product currency does not match order currency.');
        }

        $quantity = (float) $data['quantity'];
        if ($quantity <= 0) {
            abort(422, 'Quantity must be strictly positive.');
        }

        $lineTotal = (int) round($product->price_minor * $quantity);
        $taxMinor = $this->taxMinorFor($product, $lineTotal, $actor->company_id);

        $item = DB::transaction(function () use ($order, $product, $data, $quantity, $lineTotal, $taxMinor): RestaurantOrderItem {
            $maxIndex = (int) RestaurantOrderItem::query()
                ->where('company_id', $order->company_id)
                ->where('order_id', $order->id)
                ->where('product_id', $product->id)
                ->max('line_index');

            $item = RestaurantOrderItem::query()->create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'menu_id' => $data['menu_id'] ?? null,
                'quantity' => $quantity,
                'unit_price_minor' => $product->price_minor,
                'line_total_minor' => $lineTotal,
                'tax_rate_id' => $product->tax_rate_id,
                'tax_minor' => $taxMinor,
                'status' => OrderItemStatus::ACTIVE->value,
                'line_index' => $maxIndex + 1,
            ]);

            $this->recalculateOrderTotals($order);

            return $item;
        });

        return $item;
    }

    /**
     * TVA serveur : line_total × rate_bps / 10 000 (taux du produit).
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

    private function recalculateOrderTotals(RestaurantOrder $order): void
    {
        $order->load('items');
        $totals = $this->calculator->calculate($order);

        $order->forceFill([
            'subtotal_minor' => $totals['subtotal_minor'],
            'tax_minor' => $totals['tax_minor'],
            'discount_minor' => $totals['discount_minor'],
            'total_minor' => $totals['total_minor'],
        ])->save();
    }
}
