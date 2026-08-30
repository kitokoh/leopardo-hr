<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Modules\RestaurantManager\Application\Services\BillCalculator;
use App\Modules\RestaurantManager\Application\Services\StockDecrementer;
use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenu;
use App\Modules\RestaurantManager\Domain\Models\RestaurantMenuItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderPayment;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Domain\Payments\InitiatePaymentRequest;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-805 (#6226) — commande en ligne publique (menu par tenant via token
 * signé, panier, paiement via le contrat PaymentGatewayInterface).
 *
 * Invariants (mêmes que le guichet RESTO-402/403/407) :
 * - prix unitaires et TVA SERVEUR (jamais acceptés du client) ;
 * - idempotence par `idempotency_key` (rejeu sans doublon) ;
 * - commande marketplace/web → MÊME workflow interne (statuts, stock,
 *   événement `restaurant.order.created.v1`) ;
 * - aucun accès inter-tenant : chaque requête est bornée au `company_id`
 *   résolu depuis le token signé (cross-tenant impossible).
 */
final class RestaurantPublicOrderService
{
    public const EVENT_ORDER_CREATED = 'restaurant.order.created.v1';

    public function __construct(
        private readonly BillCalculator $calculator,
        private readonly StockDecrementer $stockDecrementer,
        private readonly RestaurantOutboxPublisher $outbox,
        private readonly PaymentGatewayRegistry $gateways,
    ) {
    }

    /**
     * Menu public d'un tenant (branches → menus → articles).
     *
     * @return array<string, mixed>
     */
    public function menu(string $companyId): array
    {
        $branches = RestaurantBranch::query()
            ->where('company_id', $companyId)
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->orderBy('name')
            ->get();

        return [
            'currency' => $branches->first()?->currency,
            'branches' => $branches->map(fn (RestaurantBranch $branch): array => [
                'branch_id' => (int) $branch->getAttribute('id'),
                'name' => $branch->name,
                'currency' => $branch->currency,
                'menus' => RestaurantMenu::query()
                    ->where('company_id', $companyId)
                    ->where('branch_id', $branch->getAttribute('id'))
                    ->where('status', RestaurantRecordStatus::ACTIVE->value)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (RestaurantMenu $menu): array => $this->menuPayload($menu, $companyId))
                    ->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Création d'une commande en ligne (panier validé serveur).
     *
     * @param  array{branch_id: int, order_type: string, items: list<array{menu_item_id: int, quantity: float|string}>, customer_name?: string|null, customer_phone?: string|null, customer_email?: string|null, note?: string|null, idempotency_key?: string|null, source?: string}  $data
     */
    public function createOrder(string $companyId, array $data): RestaurantOrder
    {
        if (! empty($data['idempotency_key'])) {
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
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->findOrFail((int) $data['branch_id']);

        $source = $data['source'] ?? OrderSource::WEB->value;
        abort_if(! in_array($source, [OrderSource::WEB->value, OrderSource::KIOSK->value], true), 422, 'Invalid public order source.');

        $customer = trim(($data['customer_name'] ?? '').' '.($data['customer_phone'] ?? ''));

        $order = DB::transaction(function () use ($companyId, $branch, $data, $source, $customer): RestaurantOrder {
            /** @var RestaurantOrder $order */
            $order = RestaurantOrder::query()->create([
                'company_id' => $companyId,
                'branch_id' => (int) $branch->getAttribute('id'),
                'order_type' => $data['order_type'],
                'status' => OrderStatus::DRAFT->value,
                'currency' => $branch->currency ?: 'DZD',
                'source' => $source,
                'note_redacted' => $customer !== '' ? 'Client: '.substr($customer, 0, 120) : ($data['note'] ?? null),
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $this->addItem($order, (int) $item['menu_item_id'], (float) $item['quantity']);
            }

            // Totaux recalculés serveur (jamais acceptés du client).
            $totals = $this->calculator->calculate($order);
            $order->forceFill([
                'subtotal_minor' => $totals['subtotal_minor'],
                'tax_minor' => $totals['tax_minor'],
                'discount_minor' => $totals['discount_minor'],
                'total_minor' => $totals['total_minor'],
                'status' => OrderStatus::OPEN->value,
            ])->save();

            // Décrément de stock + événement outbox (même workflow interne).
            $this->stockDecrementer->decrementForOrder($order);

            return $order;
        });

        $this->outbox->publish($companyId, self::EVENT_ORDER_CREATED, [
            'order_reference' => $order->reference,
            'branch_id' => (int) $order->getAttribute('branch_id'),
            'source' => $source,
            'total_minor' => (int) $order->total_minor,
            'currency' => $order->currency,
        ], 'public-order-'.(int) $order->getAttribute('id'));

        return $order->load('items');
    }

    /**
     * Initiation du paiement en ligne (mobile money par défaut).
     *
     * @param  array{provider_code?: string, idempotency_key?: string|null}  $data
     */
    public function pay(string $companyId, RestaurantOrder $order, array $data): RestaurantOrderPayment
    {
        if ($order->company_id !== $companyId) {
            abort(404);
        }

        if (! in_array($order->status, [OrderStatus::OPEN, OrderStatus::IN_PREPARATION, OrderStatus::READY, OrderStatus::SERVED], true)) {
            abort(409, sprintf('Order cannot be paid from status "%s".', $order->status->value));
        }

        $providerCode = $data['provider_code'] ?? 'mobile_money';
        abort_if(! $this->gateways->has($providerCode), 422, 'Unsupported payment provider.');

        if (! empty($data['idempotency_key'])) {
            $existing = RestaurantOrderPayment::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing instanceof RestaurantOrderPayment) {
                return $existing;
            }
        }

        $remaining = $this->remainingDue($order);
        abort_if($remaining <= 0, 409, 'Order is already fully paid.');

        $gateway = $this->gateways->resolve($providerCode);

        /** @var RestaurantOrderPayment $payment */
        $payment = RestaurantOrderPayment::query()->create([
            'company_id' => $companyId,
            'order_id' => (int) $order->getAttribute('id'),
            'pos_session_id' => $order->pos_session_id,
            'provider_code' => $providerCode,
            'amount_minor' => $remaining,
            'currency' => $order->currency,
            'status' => PaymentStatus::PENDING->value,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        $init = $gateway->initiate(new InitiatePaymentRequest(
            companyId: $companyId,
            amountMinor: (int) $payment->amount_minor,
            currency: $order->currency,
            reference: (string) $order->reference,
            idempotencyKey: (string) $payment->idempotency_key,
        ));

        $payment->forceFill(['provider_reference' => $init->providerReference])->save();

        return $payment->refresh();
    }

    private function remainingDue(RestaurantOrder $order): int
    {
        $paid = (int) RestaurantOrderPayment::query()
            ->where('company_id', $order->company_id)
            ->where('order_id', $order->getAttribute('id'))
            ->where('status', PaymentStatus::CONFIRMED->value)
            ->sum('amount_minor');

        return (int) $order->total_minor - $paid;
    }

    private function addItem(RestaurantOrder $order, int $menuItemId, float $quantity): void
    {
        abort_if($quantity <= 0, 422, 'Quantity must be strictly positive.');

        $menuItem = RestaurantMenuItem::query()
            ->where('company_id', $order->company_id)
            ->whereHas('menu', fn (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder => $query->where('branch_id', $order->branch_id))
            ->findOrFail($menuItemId);

        /** @var RestaurantProduct $product */
        $product = RestaurantProduct::query()
            ->where('company_id', $order->company_id)
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->where('is_available', true)
            ->findOrFail($menuItem->product_id);

        if ($product->branch_id !== null && $product->branch_id !== $order->branch_id) {
            abort(422, 'Product is not served by this branch.');
        }

        /** @var RestaurantTaxRate|null $taxRate */
        $taxRate = $product->tax_rate_id !== null
            ? RestaurantTaxRate::query()->where('company_id', $order->company_id)->find($product->tax_rate_id)
            : null;

        $unitPrice = (int) $product->price_minor;
        $lineTotal = (int) round($unitPrice * $quantity);
        $taxMinor = $taxRate !== null ? (int) round($lineTotal * ((int) $taxRate->rate_bps / 10000)) : 0;

        RestaurantOrderItem::query()->create([
            'company_id' => $order->company_id,
            'order_id' => (int) $order->getAttribute('id'),
            'product_id' => (int) $product->getAttribute('id'),
            'menu_id' => (int) $menuItem->getAttribute('menu_id'),
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice,
            'line_total_minor' => $lineTotal,
            'tax_rate_id' => $taxRate !== null ? (int) $taxRate->getAttribute('id') : null,
            'tax_minor' => $taxMinor,
            'status' => OrderItemStatus::ACTIVE->value,
            'line_index' => $order->items()->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function menuPayload(RestaurantMenu $menu, string $companyId): array
    {
        $items = $menu->items()
            ->with('product')
            ->get()
            ->filter(fn ($item): bool => $item->product !== null
                && $item->product->status === RestaurantRecordStatus::ACTIVE
                && $item->product->is_available)
            ->map(fn ($item): array => [
                'menu_item_id' => (int) $item->getAttribute('id'),
                'product_id' => (int) $item->product->getAttribute('id'),
                'name' => $item->product->name,
                'price_minor' => (int) $item->product->price_minor,
                'currency' => $item->product->currency ?: $menu->currency,
            ])
            ->values()
            ->all();

        return [
            'menu_id' => (int) $menu->getAttribute('id'),
            'code' => $menu->code,
            'name' => $menu->name,
            'currency' => $menu->currency,
            'items' => $items,
        ];
    }
}
