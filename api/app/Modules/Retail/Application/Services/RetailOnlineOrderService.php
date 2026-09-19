<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Enums\RetailOrderSource;
use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Enums\RetailStockReasonCode;
use App\Modules\Retail\Domain\Exceptions\InvalidRetailFulfillmentTransition;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Domain\Models\RetailProduct;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7808) — Orchestration des commandes EN LIGNE du
 * marketplace public : checkout invite et machine d'etats vendeur.
 *
 * Regles (spec MARKETPLACE_RETAIL_PUBLIC §2.3/§3.3) :
 * - 1 commande = 1 vendeur : tous les produits appartiennent au tenant de
 *   la boutique, sont `published` ET `online_visible` ;
 * - les prix sont RELUS EN BASE (jamais acceptes du client), totaux calcules
 *   serveur en minor units ; `source=online`, `fulfillment_status=pending`,
 *   reference `WEB-YYYYMMDD-XXXXXX`, `tracking_token` 64 hex remis au seul
 *   invite ; idempotence par cle (rejeu => meme commande) ;
 * - transitions : `confirm` decremente le stock (mouvements `sale` via
 *   RetailStockService — un stock insuffisant REFUSE la confirmation, 422),
 *   `cancel` restaure le stock si la commande etait confirmee (mouvements
 *   `return`), toute transition hors du graphe => InvalidRetailFulfillmentTransition
 *   (422 INVALID_TRANSITION cote controleur). Chaque transition est
 *   horodatee (`*_at`) et incremente `version` (piste d'audit, pattern #7674).
 *
 * Chaque methode publique est TRANSACTIONNELLE. Pas de facade Laravel ici
 * (purete de couche Application, garde #6568) : la connexion est injectee
 * via ConnectionInterface.
 */
final class RetailOnlineOrderService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly RetailStockService $stockService,
    ) {}

    /**
     * Checkout invite : cree la commande en ligne `pending` chez le tenant
     * de la boutique. Rejeu idempotent par (tenant, idempotency_key).
     *
     * @param  list<array{product_id: int, quantity: float}>  $lines
     * @param  array{name: string, phone: string, email?: string|null}  $customer
     * @param  array{address?: string|null, city?: string|null, note?: string|null}  $delivery
     *
     * @throws ValidationException 422 (produit non visible en ligne, devises melangees, boutique sans emplacement).
     */
    public function createGuestOrder(
        RetailOnlineSettings $settings,
        array $lines,
        array $customer,
        array $delivery,
        string $idempotencyKey,
    ): RetailOrder {
        $companyId = (string) $settings->company_id;

        /** @var RetailOrder $order */
        $order = $this->connection->transaction(
            function () use ($settings, $companyId, $lines, $customer, $delivery, $idempotencyKey): RetailOrder {
                /** @var RetailOrder|null $existing */
                $existing = RetailOrder::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', $companyId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing instanceof RetailOrder) {
                    return $existing;
                }

                $location = $this->fulfillmentLocation($settings);

                $currency = null;
                $subtotal = 0;
                $itemRows = [];

                foreach ($lines as $index => $line) {
                    /** @var RetailProduct|null $product */
                    $product = RetailProduct::query()
                        ->withoutGlobalScope('company')
                        ->where('company_id', $companyId)
                        ->find($line['product_id']);

                    if (! $product instanceof RetailProduct
                        || $product->status !== RetailProductStatus::Published
                        || ! $product->online_visible) {
                        // Fail-closed : produit inconnu chez CE vendeur, non
                        // publie ou non visible en ligne — meme message.
                        throw ValidationException::withMessages([
                            'lines.'.$index.'.product_id' => 'Product is not available in this shop.',
                        ]);
                    }

                    if ($currency === null) {
                        $currency = $product->currency;
                    } elseif ($currency !== $product->currency) {
                        throw ValidationException::withMessages([
                            'lines.'.$index.'.product_id' => 'All order lines must share the same currency.',
                        ]);
                    }

                    $quantity = round((float) $line['quantity'], 3);
                    $lineTotal = (int) round($quantity * $product->price_minor);
                    $subtotal += $lineTotal;

                    $itemRows[] = [
                        'product_id' => (int) $product->id,
                        'product_name' => $product->name,
                        'quantity' => number_format($quantity, 3, '.', ''),
                        'unit_price_minor' => (int) $product->price_minor,
                        'line_total_minor' => $lineTotal,
                        'line_index' => $index,
                    ];
                }

                $order = RetailOrder::query()->create([
                    'company_id' => $companyId,
                    'location_id' => (int) $location->id,
                    'pos_session_id' => null,
                    'reference' => $this->generateReference($companyId),
                    'status' => RetailOrderStatus::Draft->value,
                    'subtotal_minor' => $subtotal,
                    'discount_minor' => 0,
                    'total_minor' => $subtotal,
                    'currency' => $currency ?? 'XOF',
                    'source' => RetailOrderSource::Online->value,
                    'idempotency_key' => $idempotencyKey,
                    'version' => 1,
                    'customer_name' => $customer['name'],
                    'customer_phone' => $customer['phone'],
                    'customer_email' => $customer['email'] ?? null,
                    'delivery_address' => $delivery['address'] ?? null,
                    'delivery_city' => $delivery['city'] ?? null,
                    'delivery_note' => $delivery['note'] ?? null,
                    'fulfillment_status' => RetailFulfillmentStatus::Pending->value,
                    'tracking_token' => bin2hex(random_bytes(32)),
                ]);

                foreach ($itemRows as $row) {
                    RetailOrderItem::query()->create([
                        'company_id' => $companyId,
                        'order_id' => (int) $order->id,
                        ...$row,
                    ]);
                }

                return $order;
            }
        );

        return $order;
    }

    /**
     * Applique une transition de la machine d'etats (spec §3.3).
     *
     * - `confirmed` : decremente le stock de chaque ligne (mouvement `sale`,
     *   reference retail_order). Contrairement au POS (survente encaissee,
     *   #7674), une commande web N'EST PAS encore encaissee : un stock
     *   insuffisant REFUSE la confirmation (ValidationException 422, rien
     *   n'est persiste) ;
     * - `cancelled` : restaure le stock (mouvements `return`) si la commande
     *   etait confirmee/prete ; passe aussi `status=cancelled` ;
     * - `delivered` : passe aussi `status=completed`.
     *
     * @throws InvalidRetailFulfillmentTransition transition hors du graphe (422 INVALID_TRANSITION).
     * @throws ValidationException 422 stock insuffisant a la confirmation.
     */
    public function transition(
        RetailOrder $order,
        RetailFulfillmentStatus $target,
        ?int $userId = null,
    ): RetailOrder {
        /** @var RetailOrder $updated */
        $updated = $this->connection->transaction(
            function () use ($order, $target, $userId): RetailOrder {
                /** @var RetailOrder $locked */
                $locked = RetailOrder::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', (string) $order->company_id)
                    ->lockForUpdate()
                    ->findOrFail((int) $order->id);

                $current = $locked->fulfillment_status;

                if (! $current instanceof RetailFulfillmentStatus || ! $current->canTransitionTo($target)) {
                    throw new InvalidRetailFulfillmentTransition(
                        $current ?? RetailFulfillmentStatus::Cancelled,
                        $target,
                    );
                }

                if ($target === RetailFulfillmentStatus::Confirmed) {
                    $this->applyStockMovements($locked, -1.0, RetailStockReasonCode::Sale, null, $userId);
                }

                if ($target === RetailFulfillmentStatus::Cancelled && $current->stockCommitted()) {
                    $this->applyStockMovements(
                        $locked,
                        1.0,
                        RetailStockReasonCode::Return,
                        'Stock restored on cancellation of online order '.$locked->reference.'.',
                        $userId,
                    );
                }

                $attributes = [
                    'fulfillment_status' => $target->value,
                    $this->timestampColumn($target) => Carbon::now(),
                    'version' => $locked->version + 1,
                ];

                if ($target === RetailFulfillmentStatus::Delivered) {
                    $attributes['status'] = RetailOrderStatus::Completed->value;
                }

                if ($target === RetailFulfillmentStatus::Cancelled) {
                    $attributes['status'] = RetailOrderStatus::Cancelled->value;
                }

                $locked->forceFill($attributes)->save();

                return $locked->refresh();
            }
        );

        return $updated;
    }

    /**
     * Mouvements de stock signes pour chaque ligne de la commande
     * (`$direction` -1 = vente, +1 = restauration).
     */
    private function applyStockMovements(
        RetailOrder $order,
        float $direction,
        RetailStockReasonCode $reasonCode,
        ?string $note,
        ?int $userId,
    ): void {
        $companyId = (string) $order->company_id;

        /** @var RetailLocation $location */
        $location = RetailLocation::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->findOrFail((int) $order->location_id);

        $items = RetailOrderItem::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('order_id', (int) $order->id)
            ->orderBy('line_index')
            ->get();

        foreach ($items as $item) {
            /** @var RetailProduct $product */
            $product = RetailProduct::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->findOrFail((int) $item->product_id);

            $this->stockService->applyMovement(
                location: $location,
                product: $product,
                quantityDelta: $direction * (float) $item->quantity,
                reasonCode: $reasonCode,
                referenceType: 'retail_order',
                referenceId: (int) $order->id,
                note: $note,
                userId: $userId,
            );
        }
    }

    /**
     * Emplacement qui sert les commandes en ligne : celui des settings,
     * sinon le premier emplacement actif du tenant (422 si aucun — la
     * boutique ne peut pas honorer de commande sans stock adresse).
     */
    private function fulfillmentLocation(RetailOnlineSettings $settings): RetailLocation
    {
        $companyId = (string) $settings->company_id;

        if ($settings->location_id !== null) {
            /** @var RetailLocation|null $configured */
            $configured = RetailLocation::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->find((int) $settings->location_id);

            if ($configured instanceof RetailLocation) {
                return $configured;
            }
        }

        /** @var RetailLocation|null $fallback */
        $fallback = RetailLocation::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $fallback instanceof RetailLocation) {
            throw ValidationException::withMessages([
                'seller' => 'This shop cannot accept online orders yet (no stock location).',
            ]);
        }

        return $fallback;
    }

    private function timestampColumn(RetailFulfillmentStatus $target): string
    {
        return match ($target) {
            RetailFulfillmentStatus::Confirmed => 'confirmed_at',
            RetailFulfillmentStatus::Ready => 'ready_at',
            RetailFulfillmentStatus::Shipped => 'shipped_at',
            RetailFulfillmentStatus::Delivered => 'delivered_at',
            RetailFulfillmentStatus::Cancelled => 'cancelled_at',
            RetailFulfillmentStatus::Pending => 'created_at',
        };
    }

    /**
     * Reference de commande web unique par tenant : WEB-YYYYMMDD-XXXXXX
     * (pattern POS #7674, re-tirage en cas de collision — la contrainte
     * unique en base est le filet de securite).
     */
    private function generateReference(string $companyId): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';

            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $reference = 'WEB-'.Carbon::now()->format('Ymd').'-'.$suffix;

            $exists = RetailOrder::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('reference', $reference)
                ->exists();

            if (! $exists) {
                return $reference;
            }
        }

        throw ValidationException::withMessages([
            'reference' => 'Unable to generate a unique order reference; please retry.',
        ]);
    }
}
