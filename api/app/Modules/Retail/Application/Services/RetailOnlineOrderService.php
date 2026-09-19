<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Events\RetailOnlineOrderConfirmed;
use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use App\Modules\Retail\Domain\Enums\RetailOrderSource;
use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Enums\RetailStockReasonCode;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7808) — Orchestration des commandes en ligne Leopardo
 * Marche : checkout invite, machine d'etats logistique, stock.
 *
 * Chaque methode publique est TRANSACTIONNELLE. Les totaux sont TOUJOURS
 * calcules serveur (prix relus en base, minor units — jamais de confiance
 * client). Le stock n'est ecrit QUE via RetailStockService (regle #7673) :
 * la confirmation decremente (mouvements `sale`), l'annulation
 * post-confirmation restaure (mouvements `return`) — voie identique au POS
 * (RetailPosService #7674), y compris la politique de survente tracee
 * (mouvement `adjustment` auto avant la vente : le vendeur qui CONFIRME
 * atteste detenir physiquement les articles).
 *
 * Machine d'etats (`RetailFulfillmentStatus`, spec §2.3) :
 * pending → confirmed → ready → shipped → delivered (+ cancelled). Toute
 * transition invalide leve une ValidationException 422 INVALID_TRANSITION.
 * Le statut historique `RetailOrderStatus` reste la source du stock :
 * draft a la creation, completed a la confirmation, cancelled a l'annulation.
 *
 * Pas de facade Laravel ici (purete de couche Application, garde #6568) :
 * la connexion est injectee via ConnectionInterface.
 */
final class RetailOnlineOrderService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly RetailStockService $stockService,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Checkout invite (POST /public/market/orders, spec §3.2) : cree la
     * commande `source = online`, `status = draft`,
     * `fulfillment_status = pending`, reference `WEB-...` et
     * `tracking_token` aleatoire (64 hex). Idempotence : si la cle existe
     * deja pour ce tenant, la commande existante est retournee avec
     * `created = false` (rejeu → 200 meme payload cote controleur).
     *
     * @param  list<array{product_id: int, quantity: int}>  $items
     * @param  array{name: string, phone: string, email: string|null}  $customer
     * @param  array{address: string, city: string, notes: string|null}  $delivery
     * @return array{order: RetailOrder, created: bool}
     *
     * @throws ValidationException 422 (produit indisponible, devises melangees, vendeur sans emplacement).
     */
    public function createGuestOrder(
        string $companyId,
        array $items,
        array $customer,
        array $delivery,
        string $idempotencyKey,
    ): array {
        /** @var array{order: RetailOrder, created: bool} $result */
        $result = $this->connection->transaction(
            function () use ($companyId, $items, $customer, $delivery, $idempotencyKey): array {
                /** @var RetailOrder|null $existing */
                $existing = RetailOrder::query()
                    ->where('company_id', $companyId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing instanceof RetailOrder) {
                    return ['order' => $existing, 'created' => false];
                }

                $location = $this->defaultLocation($companyId);

                $currency = null;
                $subtotal = 0;
                $itemRows = [];

                foreach ($items as $index => $item) {
                    /** @var RetailProduct|null $product */
                    $product = RetailProduct::query()
                        ->where('company_id', $companyId)
                        ->find($item['product_id']);

                    if (! $product instanceof RetailProduct
                        || $product->status !== RetailProductStatus::Published
                        || ! $product->online_visible) {
                        // Produit inconnu chez CE vendeur, non publie ou non
                        // visible en ligne : refus uniforme (pas de probing).
                        throw ValidationException::withMessages([
                            'items.'.$index.'.product_id' => 'PRODUCT_NOT_AVAILABLE',
                        ]);
                    }

                    if ($currency === null) {
                        $currency = $product->currency;
                    } elseif ($currency !== $product->currency) {
                        throw ValidationException::withMessages([
                            'items.'.$index.'.product_id' => 'CURRENCY_MISMATCH',
                        ]);
                    }

                    $quantity = $item['quantity'];
                    $lineTotal = $quantity * (int) $product->price_minor;
                    $subtotal += $lineTotal;

                    $itemRows[] = [
                        'product_id' => (int) $product->id,
                        'product_name' => $product->name,
                        'quantity' => number_format((float) $quantity, 3, '.', ''),
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
                    'currency' => $currency ?? 'DZD',
                    'source' => RetailOrderSource::Online->value,
                    'idempotency_key' => $idempotencyKey,
                    'customer_name' => $customer['name'],
                    'customer_phone' => $customer['phone'],
                    'customer_email' => $customer['email'],
                    'delivery_address' => $delivery['address'],
                    'delivery_city' => $delivery['city'],
                    'delivery_notes' => $delivery['notes'],
                    'fulfillment_status' => RetailFulfillmentStatus::Pending->value,
                    'tracking_token' => bin2hex(random_bytes(32)),
                    'version' => 1,
                ]);

                foreach ($itemRows as $row) {
                    RetailOrderItem::query()->create([
                        'company_id' => $companyId,
                        'order_id' => (int) $order->id,
                        ...$row,
                    ]);
                }

                return ['order' => $order, 'created' => true];
            }
        );

        return $result;
    }

    /**
     * `pending → confirmed` : le vendeur accepte la commande. Decremente le
     * stock de chaque ligne (mouvements `sale` via RetailStockService, sur
     * l'emplacement fourni ou l'emplacement de la commande) et passe le
     * statut historique a `completed` + `confirmed_at` (spec §2.3).
     *
     * APRES commit, dispatch `RetailOnlineOrderConfirmed` (#7811) : le
     * handoff BC-26 (creation de la livraison `source=retail_online`) est
     * consomme par le module Delivery — integration par evenement, jamais
     * d'import cross-module (registre BC). Le montant COD est le solde non
     * encaisse (v1 : tout le total — paiement a la livraison).
     *
     * @throws ValidationException 422 INVALID_TRANSITION.
     */
    public function confirm(RetailOrder $order, ?RetailLocation $location = null, ?int $userId = null): RetailOrder
    {
        /** @var RetailOrder $updated */
        $updated = $this->connection->transaction(
            function () use ($order, $location, $userId): RetailOrder {
                $locked = $this->lockedOnlineOrder($order);

                $this->assertTransition($locked, RetailFulfillmentStatus::Confirmed);

                $target = $location instanceof RetailLocation
                    ? $location
                    : $this->orderLocation($locked);

                if ($location instanceof RetailLocation
                    && (int) $location->id !== (int) $locked->location_id) {
                    // Emplacement de preparation different de celui retenu au
                    // checkout : la commande est re-rattachee pour que
                    // l'annulation restaure le stock au BON endroit.
                    $locked->forceFill(['location_id' => (int) $location->id])->save();
                }

                $this->decrementStock($locked, $target, $userId);

                $locked->forceFill([
                    'status' => RetailOrderStatus::Completed->value,
                    'fulfillment_status' => RetailFulfillmentStatus::Confirmed->value,
                    'confirmed_at' => Carbon::now(),
                    'version' => $locked->version + 1,
                ])->save();

                return $locked->refresh();
            }
        );

        $this->events->dispatch(new RetailOnlineOrderConfirmed(
            companyId: (string) $updated->company_id,
            orderId: (int) $updated->id,
            reference: $updated->reference,
            totalMinor: (int) $updated->total_minor,
            currency: $updated->currency,
            codAmountMinor: $this->outstandingAmountMinor($updated),
            customerName: $updated->customer_name,
            customerPhone: $updated->customer_phone,
            deliveryAddress: $updated->delivery_address,
            deliveryCity: $updated->delivery_city,
            deliveryNotes: $updated->delivery_notes,
        ));

        return $updated;
    }

    /**
     * Solde restant a encaisser a la livraison (COD) : total moins paiements
     * captures (#7812 — un paiement en ligne reussi annule le COD). Jamais
     * negatif.
     */
    private function outstandingAmountMinor(RetailOrder $order): int
    {
        $captured = (int) RetailOrderPayment::query()
            ->where('company_id', (string) $order->company_id)
            ->where('order_id', (int) $order->id)
            ->where('status', 'captured')
            ->sum('amount_minor');

        return max(0, (int) $order->total_minor - $captured);
    }

    /**
     * Progression logistique `confirmed → ready`, `→ shipped`,
     * `→ delivered` (horodatages `shipped_at`/`delivered_at`).
     *
     * @throws ValidationException 422 INVALID_TRANSITION.
     */
    public function progress(RetailOrder $order, RetailFulfillmentStatus $target): RetailOrder
    {
        /** @var RetailOrder $updated */
        $updated = $this->connection->transaction(
            function () use ($order, $target): RetailOrder {
                $locked = $this->lockedOnlineOrder($order);

                $this->assertTransition($locked, $target);

                $attributes = [
                    'fulfillment_status' => $target->value,
                    'version' => $locked->version + 1,
                ];

                if ($target === RetailFulfillmentStatus::Shipped) {
                    $attributes['shipped_at'] = Carbon::now();
                }

                if ($target === RetailFulfillmentStatus::Delivered) {
                    $attributes['delivered_at'] = Carbon::now();
                }

                $locked->forceFill($attributes)->save();

                return $locked->refresh();
            }
        );

        return $updated;
    }

    /**
     * Annulation : depuis `pending`, simple bascule (aucun mouvement de
     * stock) ; apres confirmation (`confirmed`/`ready`), mouvements
     * `return` restaurant le stock de chaque ligne — meme voie que
     * l'annulation POS (RetailPosService::cancelOrder #7674).
     *
     * @throws ValidationException 422 INVALID_TRANSITION.
     */
    public function cancel(RetailOrder $order, ?string $note = null, ?int $userId = null): RetailOrder
    {
        /** @var RetailOrder $updated */
        $updated = $this->connection->transaction(
            function () use ($order, $note, $userId): RetailOrder {
                $locked = $this->lockedOnlineOrder($order);

                $this->assertTransition($locked, RetailFulfillmentStatus::Cancelled);

                if ($locked->status === RetailOrderStatus::Completed) {
                    $location = $this->orderLocation($locked);

                    foreach ($locked->items()->orderBy('line_index')->get() as $item) {
                        /** @var RetailProduct $product */
                        $product = RetailProduct::query()
                            ->where('company_id', (string) $locked->company_id)
                            ->findOrFail($item->product_id);

                        $this->stockService->applyMovement(
                            location: $location,
                            product: $product,
                            quantityDelta: (float) $item->quantity,
                            reasonCode: RetailStockReasonCode::Return,
                            referenceType: 'retail_order',
                            referenceId: (int) $locked->id,
                            note: 'Stock restored on cancellation of online order '.$locked->reference.'.',
                            userId: $userId,
                        );
                    }
                }

                $locked->forceFill([
                    'status' => RetailOrderStatus::Cancelled->value,
                    'fulfillment_status' => RetailFulfillmentStatus::Cancelled->value,
                    'note' => $note ?? $locked->note,
                    'version' => $locked->version + 1,
                ])->save();

                return $locked->refresh();
            }
        );

        return $updated;
    }

    /**
     * Decremente le stock de chaque ligne (mouvement `sale`) — survente :
     * ajustement trace jusqu'a la quantite vendue puis vente, exactement
     * comme la completion POS (cf. docblock de classe).
     */
    private function decrementStock(RetailOrder $order, RetailLocation $location, ?int $userId): void
    {
        $companyId = (string) $order->company_id;

        foreach ($order->items()->orderBy('line_index')->get() as $item) {
            /** @var RetailProduct $product */
            $product = RetailProduct::query()
                ->where('company_id', $companyId)
                ->findOrFail($item->product_id);

            $quantity = (float) $item->quantity;

            try {
                $this->stockService->applyMovement(
                    location: $location,
                    product: $product,
                    quantityDelta: -$quantity,
                    reasonCode: RetailStockReasonCode::Sale,
                    referenceType: 'retail_order',
                    referenceId: (int) $order->id,
                    note: null,
                    userId: $userId,
                );
            } catch (ValidationException) {
                $current = $this->currentQuantity($companyId, (int) $location->id, (int) $product->id);
                $shortfall = round($quantity - $current, 3);

                $this->stockService->applyMovement(
                    location: $location,
                    product: $product,
                    quantityDelta: $shortfall,
                    reasonCode: RetailStockReasonCode::Adjustment,
                    referenceType: 'retail_order',
                    referenceId: (int) $order->id,
                    note: 'Auto adjustment on oversell (online order '.$order->reference.').',
                    userId: $userId,
                );

                $this->stockService->applyMovement(
                    location: $location,
                    product: $product,
                    quantityDelta: -$quantity,
                    reasonCode: RetailStockReasonCode::Sale,
                    referenceType: 'retail_order',
                    referenceId: (int) $order->id,
                    note: null,
                    userId: $userId,
                );
            }
        }
    }

    /**
     * Recharge la commande AVEC verrou de ligne (les actions vendeur
     * concurrentes sont serialisees) — commandes `online` uniquement.
     *
     * @throws ValidationException 422 si la commande n'est pas du canal en ligne.
     */
    private function lockedOnlineOrder(RetailOrder $order): RetailOrder
    {
        /** @var RetailOrder $locked */
        $locked = RetailOrder::query()
            ->where('company_id', (string) $order->company_id)
            ->lockForUpdate()
            ->findOrFail((int) $order->id);

        if ($locked->source !== RetailOrderSource::Online) {
            throw ValidationException::withMessages([
                'order' => 'ORDER_NOT_ONLINE',
            ]);
        }

        return $locked;
    }

    /**
     * @throws ValidationException 422 INVALID_TRANSITION si la machine
     *                             d'etats refuse la transition demandee.
     */
    private function assertTransition(RetailOrder $order, RetailFulfillmentStatus $target): void
    {
        $current = $order->fulfillment_status;

        if (! $current instanceof RetailFulfillmentStatus
            || ! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'fulfillment_status' => 'INVALID_TRANSITION',
            ]);
        }
    }

    /**
     * Emplacement par defaut du vendeur pour rattacher la commande en ligne
     * (premier emplacement ACTIF, sinon premier emplacement).
     *
     * @throws ValidationException 422 si le vendeur n'a aucun emplacement.
     */
    private function defaultLocation(string $companyId): RetailLocation
    {
        /** @var RetailLocation|null $location */
        $location = RetailLocation::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $location instanceof RetailLocation) {
            /** @var RetailLocation|null $location */
            $location = RetailLocation::query()
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->first();
        }

        if (! $location instanceof RetailLocation) {
            throw ValidationException::withMessages([
                'seller' => 'SELLER_NOT_READY',
            ]);
        }

        return $location;
    }

    private function orderLocation(RetailOrder $order): RetailLocation
    {
        /** @var RetailLocation $location */
        $location = RetailLocation::query()
            ->where('company_id', (string) $order->company_id)
            ->findOrFail((int) $order->location_id);

        return $location;
    }

    /**
     * Quantite en stock courante (0 si aucun niveau n'existe encore).
     */
    private function currentQuantity(string $companyId, int $locationId, int $productId): float
    {
        /** @var RetailStockLevel|null $level */
        $level = RetailStockLevel::query()
            ->where('company_id', $companyId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->first();

        return $level instanceof RetailStockLevel ? (float) $level->quantity : 0.0;
    }

    /**
     * Reference de commande web unique par tenant : WEB-YYYYMMDD-XXXXXX
     * (meme alphabet que le POS #7674, re-tirage en cas de collision — la
     * contrainte unique en base est le filet de securite).
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
