<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Application\Services\BillCalculator;
use App\Modules\RestaurantManager\Domain\Enums\OrderItemStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrderItem;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTaxRate;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-805 (#6226) — Commande en ligne publique (menu public par tenant).
 *
 * Crée une commande SANS utilisateur authentifié : le tenant est résolu par
 * le jeton de boutique (middleware `restaurant.public.shop`), le scope
 * global BelongsToCompany s'applique → aucune fuite cross-tenant. Le montant
 * est calculé côté serveur (prix du référentiel, BillCalculator) — jamais
 * accepté du client. Idempotence : une `idempotency_key` déjà utilisée pour
 * le tenant renvoie la commande existante (rejeu sans doublon). Un
 * événement outbox `restaurant.order.created.v1` est publié APRÈS le commit
 * (consommateurs : cuisine, notifications — pattern RESTO-404/#6191).
 */
final class RestaurantPublicOrderService
{
    public function __construct(
        private readonly BillCalculator $calculator,
        private readonly RestaurantOutboxPublisher $outbox,
    ) {
    }

    /**
     * @param  list<array{product_code: string, quantity: float|string, menu_id?: int|null}>  $items
     * @return array{order: RestaurantOrder, created: bool}
     */
    public function create(string $companyId, OrderSource $source, array $items, ?string $idempotencyKey = null, ?int $branchId = null, ?string $customerPhone = null): array
    {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = RestaurantOrder::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof RestaurantOrder) {
                return ['order' => $existing, 'created' => false];
            }
        }

        if ($items === []) {
            abort(422, 'Panier vide.');
        }

        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            abort(401, 'Tenant introuvable.');
        }

        $branch = $this->resolveBranch($companyId, $branchId);

        $order = DB::transaction(function () use ($company, $branch, $source, $items, $idempotencyKey, $customerPhone): RestaurantOrder {
            // Statut `open` d'emblée : une commande web/kiosque/marketplace
            // n'a pas de phase brouillon en salle — elle est immédiatement
            // visible en cuisine (start) et payable (machine à états).
            $order = RestaurantOrder::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'order_type' => OrderType::TAKEAWAY->value,
                'status' => OrderStatus::OPEN->value,
                'source' => $source->value,
                'currency' => $branch->currency ?: $company->currency,
                'idempotency_key' => $idempotencyKey,
                'note_redacted' => $customerPhone !== null ? 'Tel client: '.substr($customerPhone, 0, 6).'***' : null,
            ]);

            $index = 0;
            foreach ($items as $entry) {
                $product = $this->resolveProduct($company->id, $branch->id, (string) $entry['product_code']);

                if (! $product instanceof RestaurantProduct) {
                    abort(422, sprintf('Produit indisponible : %s.', (string) $entry['product_code']));
                }

                $quantity = (float) $entry['quantity'];
                if ($quantity <= 0) {
                    abort(422, 'Quantité strictement positive requise.');
                }

                $lineTotal = (int) round($product->price_minor * $quantity);
                $taxMinor = $this->taxMinorFor($product, $lineTotal, $company->id);
                $menuId = isset($entry['menu_id']) ? (int) $entry['menu_id'] : null;

                RestaurantOrderItem::query()->create([
                    'company_id' => $company->id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'menu_id' => $menuId,
                    'quantity' => $quantity,
                    'unit_price_minor' => $product->price_minor,
                    'line_total_minor' => $lineTotal,
                    'tax_rate_id' => $product->tax_rate_id,
                    'tax_minor' => $taxMinor,
                    'status' => OrderItemStatus::ACTIVE->value,
                    'line_index' => $index++,
                ]);
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

        // Après commit : notifier la cuisine / notifications (best-effort).
        $this->outbox->publish(
            companyId: $company->id,
            eventType: 'restaurant.order.created.v1',
            payload: [
                'company_id' => $company->id,
                'order_id' => $order->id,
                'reference' => $order->reference,
                'source' => $source->value,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
            ],
            idempotencyKey: 'public-order:'.$order->id,
        );

        return ['order' => $order, 'created' => true];
    }

    private function resolveBranch(string $companyId, ?int $branchId): RestaurantBranch
    {
        $query = RestaurantBranch::query()->where('company_id', $companyId);

        if ($branchId !== null) {
            $branch = (clone $query)->find($branchId);

            if (! $branch instanceof RestaurantBranch) {
                abort(422, 'Branche introuvable pour ce tenant.');
            }

            return $branch;
        }

        $branch = (clone $query)->orderBy('id')->first();

        if (! $branch instanceof RestaurantBranch) {
            abort(422, 'Aucune branche active pour ce tenant.');
        }

        return $branch;
    }

    private function resolveProduct(string $companyId, int $branchId, string $code): ?RestaurantProduct
    {
        $product = RestaurantProduct::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->where('status', RestaurantRecordStatus::ACTIVE->value)
            ->where('is_available', true)
            ->first();

        if (! $product instanceof RestaurantProduct) {
            return null;
        }

        if ($product->branch_id !== null && $product->branch_id !== $branchId) {
            return null;
        }

        return $product;
    }

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
