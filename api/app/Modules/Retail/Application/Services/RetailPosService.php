<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailOrderSource;
use App\Modules\Retail\Domain\Enums\RetailOrderStatus;
use App\Modules\Retail\Domain\Enums\RetailPaymentMethod;
use App\Modules\Retail\Domain\Enums\RetailPosSessionStatus;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Enums\RetailStockReasonCode;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Domain\Models\RetailOrderPayment;
use App\Modules\Retail\Domain\Models\RetailPosSession;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7674) — Orchestration POS v1 : sessions de caisse,
 * commandes de vente, paiements multi-moyens, decrement de stock.
 *
 * Chaque methode publique est TRANSACTIONNELLE. Les totaux sont TOUJOURS
 * calcules serveur (minor units, jamais de flottants persistes). Le stock
 * n'est ecrit QUE via RetailStockService (transaction imbriquee = savepoint).
 *
 * Politique de survente (decision #7674, documentee) : une vente encaissee
 * ne DOIT JAMAIS etre bloquee par un stock theorique insuffisant (realite
 * boutique : l'article est physiquement passe en caisse). Comme
 * RetailStockService refuse tout stock negatif, la completion applique
 * d'abord, si necessaire, un mouvement `adjustment` trace (note
 * "Auto adjustment on oversell...") qui remonte la quantite au niveau vendu,
 * puis le mouvement `sale`. La piste d'audit reste complete : l'ecart
 * d'inventaire est visible dans le journal des mouvements.
 *
 * Pas de facade Laravel ici (purete de couche Application, garde #6568) :
 * la connexion est injectee via ConnectionInterface.
 */
final class RetailPosService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly RetailStockService $stockService,
    ) {}

    /**
     * Ouvre une session de caisse sur un emplacement du tenant.
     *
     * Une seule session `open` par (tenant, emplacement) : pre-verification
     * applicative (422) ; l'index unique partiel Postgres est le filet de
     * securite en cas de course.
     *
     * @throws ValidationException 422 si une session est deja ouverte.
     */
    public function openSession(
        RetailLocation $location,
        int $openedByUserId,
        int $openingCashMinor,
    ): RetailPosSession {
        $companyId = (string) $location->company_id;

        /** @var RetailPosSession $session */
        $session = $this->connection->transaction(
            function () use ($location, $companyId, $openedByUserId, $openingCashMinor): RetailPosSession {
                $alreadyOpen = RetailPosSession::query()
                    ->where('company_id', $companyId)
                    ->where('location_id', (int) $location->id)
                    ->where('status', RetailPosSessionStatus::Open->value)
                    ->exists();

                if ($alreadyOpen) {
                    throw ValidationException::withMessages([
                        'location_id' => 'A POS session is already open for this location.',
                    ]);
                }

                return RetailPosSession::query()->create([
                    'company_id' => $companyId,
                    'location_id' => (int) $location->id,
                    'opened_at' => Carbon::now(),
                    'opened_by_user_id' => $openedByUserId,
                    'opening_cash_minor' => $openingCashMinor,
                    'status' => RetailPosSessionStatus::Open->value,
                    'version' => 1,
                ]);
            }
        );

        return $session;
    }

    /**
     * Cree une commande de vente (statut `draft`) sur une session OUVERTE.
     *
     * Chaque ligne snapshotte le nom et le prix du produit (minor units) ;
     * tous les produits doivent etre publies (vendables) et partager la meme
     * devise. Si `$idempotencyKey` correspond a une commande existante du
     * tenant, cette commande est retournee telle quelle (rejeu sans doublon).
     *
     * @param  list<array{product_id: int, quantity: float}>  $lines
     *
     * @throws ValidationException 422 (session fermee, produit non vendable, devises melangees).
     */
    public function createOrder(
        RetailPosSession $session,
        array $lines,
        ?string $note = null,
        ?string $idempotencyKey = null,
    ): RetailOrder {
        $companyId = (string) $session->company_id;

        /** @var RetailOrder $order */
        $order = $this->connection->transaction(
            function () use ($session, $companyId, $lines, $note, $idempotencyKey): RetailOrder {
                if ($idempotencyKey !== null) {
                    /** @var RetailOrder|null $existing */
                    $existing = RetailOrder::query()
                        ->where('company_id', $companyId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing instanceof RetailOrder) {
                        return $existing;
                    }
                }

                if ($session->status !== RetailPosSessionStatus::Open) {
                    throw ValidationException::withMessages([
                        'session' => 'The POS session is not open.',
                    ]);
                }

                $currency = null;
                $subtotal = 0;
                $itemRows = [];

                foreach ($lines as $index => $line) {
                    /** @var RetailProduct|null $product */
                    $product = RetailProduct::query()
                        ->where('company_id', $companyId)
                        ->find($line['product_id']);

                    if (! $product instanceof RetailProduct) {
                        throw ValidationException::withMessages([
                            'lines.'.$index.'.product_id' => 'Product not found for this tenant.',
                        ]);
                    }

                    if ($product->status !== RetailProductStatus::Published) {
                        throw ValidationException::withMessages([
                            'lines.'.$index.'.product_id' => 'Product is not sellable (not published).',
                        ]);
                    }

                    if ($currency === null) {
                        $currency = $product->currency;
                    } elseif ($currency !== $product->currency) {
                        throw ValidationException::withMessages([
                            'lines.'.$index.'.product_id' => 'All order lines must share the same currency.',
                        ]);
                    }

                    $quantity = round($line['quantity'], 3);
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
                    'location_id' => (int) $session->location_id,
                    'pos_session_id' => (int) $session->id,
                    'reference' => $this->generateReference($companyId),
                    'status' => RetailOrderStatus::Draft->value,
                    'subtotal_minor' => $subtotal,
                    'discount_minor' => 0,
                    'total_minor' => $subtotal,
                    'currency' => $currency ?? 'XOF',
                    'source' => RetailOrderSource::Pos->value,
                    'note' => $note,
                    'idempotency_key' => $idempotencyKey,
                    'version' => 1,
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
     * Enregistre un paiement (statut `captured`) sur une commande `draft`.
     *
     * Si `$idempotencyKey` correspond a un paiement existant du tenant,
     * l'etat courant de la commande est retourne sans nouveau paiement.
     * Quand la somme des paiements captures atteint le total, la commande
     * passe `completed` et CHAQUE ligne genere un mouvement `sale` via
     * RetailStockService (politique de survente : cf. docblock de classe).
     *
     * @throws ValidationException 422 si la commande n'est pas en brouillon.
     */
    public function addPayment(
        RetailOrder $order,
        RetailPaymentMethod $method,
        int $amountMinor,
        ?string $idempotencyKey = null,
        ?string $reference = null,
        ?int $userId = null,
    ): RetailOrder {
        $companyId = (string) $order->company_id;

        /** @var RetailOrder $updated */
        $updated = $this->connection->transaction(
            function () use ($order, $companyId, $method, $amountMinor, $idempotencyKey, $reference, $userId): RetailOrder {
                if ($idempotencyKey !== null) {
                    $existing = RetailOrderPayment::query()
                        ->where('company_id', $companyId)
                        ->where('idempotency_key', $idempotencyKey)
                        ->exists();

                    if ($existing) {
                        return $order->refresh();
                    }
                }

                if ($order->status !== RetailOrderStatus::Draft) {
                    throw ValidationException::withMessages([
                        'order' => 'Only draft orders can receive payments.',
                    ]);
                }

                RetailOrderPayment::query()->create([
                    'company_id' => $companyId,
                    'order_id' => (int) $order->id,
                    'pos_session_id' => $order->pos_session_id,
                    'method' => $method->value,
                    'amount_minor' => $amountMinor,
                    'currency' => $order->currency,
                    'status' => 'captured',
                    'paid_at' => Carbon::now(),
                    'reference' => $reference,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $captured = (int) RetailOrderPayment::query()
                    ->where('company_id', $companyId)
                    ->where('order_id', (int) $order->id)
                    ->where('status', 'captured')
                    ->sum('amount_minor');

                if ($captured >= $order->total_minor) {
                    $this->completeOrder($order, $userId);
                }

                return $order->refresh();
            }
        );

        return $updated;
    }

    /**
     * Annule une commande. `draft` : simple passage a `cancelled` ;
     * `completed` : mouvements `return` (delta positif) restaurant le stock
     * de chaque ligne, puis `cancelled` (version++).
     *
     * @throws ValidationException 422 si la commande est deja annulee.
     */
    public function cancelOrder(RetailOrder $order, ?string $note = null, ?int $userId = null): RetailOrder
    {
        /** @var RetailOrder $updated */
        $updated = $this->connection->transaction(
            function () use ($order, $note, $userId): RetailOrder {
                if ($order->status === RetailOrderStatus::Cancelled) {
                    throw ValidationException::withMessages([
                        'order' => 'Order is already cancelled.',
                    ]);
                }

                if ($order->status === RetailOrderStatus::Completed) {
                    $location = $this->orderLocation($order);

                    foreach ($order->items()->orderBy('line_index')->get() as $item) {
                        /** @var RetailProduct $product */
                        $product = RetailProduct::query()
                            ->where('company_id', (string) $order->company_id)
                            ->findOrFail($item->product_id);

                        $this->stockService->applyMovement(
                            location: $location,
                            product: $product,
                            quantityDelta: (float) $item->quantity,
                            reasonCode: RetailStockReasonCode::Return,
                            referenceType: 'retail_order',
                            referenceId: (int) $order->id,
                            note: 'Stock restored on cancellation of completed order '.$order->reference.'.',
                            userId: $userId,
                        );
                    }
                }

                $order->forceFill([
                    'status' => RetailOrderStatus::Cancelled->value,
                    'note' => $note ?? $order->note,
                    'version' => $order->version + 1,
                ])->save();

                return $order->refresh();
            }
        );

        return $updated;
    }

    /**
     * Cloture une session OUVERTE : `expected_cash_minor` = fonds d'ouverture
     * + somme des paiements CASH captures de la session (calcul serveur pur) ;
     * `variance_minor` = compte - attendu (signe). Statut `closed`, immuable.
     *
     * @throws ValidationException 422 si la session n'est pas ouverte.
     */
    public function closeSession(
        RetailPosSession $session,
        int $countedCashMinor,
        ?string $varianceReason,
        int $closedByUserId,
    ): RetailPosSession {
        $companyId = (string) $session->company_id;

        /** @var RetailPosSession $closed */
        $closed = $this->connection->transaction(
            function () use ($session, $companyId, $countedCashMinor, $varianceReason, $closedByUserId): RetailPosSession {
                /** @var RetailPosSession $locked */
                $locked = RetailPosSession::query()
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->findOrFail((int) $session->id);

                if ($locked->status !== RetailPosSessionStatus::Open) {
                    throw ValidationException::withMessages([
                        'session' => 'The POS session is not open.',
                    ]);
                }

                $cashCaptured = (int) RetailOrderPayment::query()
                    ->where('company_id', $companyId)
                    ->where('pos_session_id', (int) $locked->id)
                    ->where('method', RetailPaymentMethod::Cash->value)
                    ->where('status', 'captured')
                    ->sum('amount_minor');

                $expected = $locked->opening_cash_minor + $cashCaptured;
                $variance = $countedCashMinor - $expected;

                $locked->forceFill([
                    'status' => RetailPosSessionStatus::Closed->value,
                    'closed_at' => Carbon::now(),
                    'closed_by_user_id' => $closedByUserId,
                    'expected_cash_minor' => $expected,
                    'counted_cash_minor' => $countedCashMinor,
                    'variance_minor' => $variance,
                    'variance_reason' => $varianceReason,
                    'version' => $locked->version + 1,
                ])->save();

                return $locked->refresh();
            }
        );

        return $closed;
    }

    /**
     * Passe la commande `completed` et decremente le stock de chaque ligne
     * (mouvement `sale`, reference retail_order). Survente : un mouvement
     * `adjustment` trace remonte d'abord la quantite au niveau vendu
     * (cf. docblock de classe) — la vente n'est JAMAIS bloquee.
     */
    private function completeOrder(RetailOrder $order, ?int $userId): void
    {
        $location = $this->orderLocation($order);
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
                // Survente : stock theorique insuffisant alors que la vente
                // est encaissee. Ajustement trace jusqu'a la quantite vendue,
                // puis mouvement de vente (piste d'audit complete).
                $current = $this->currentQuantity($companyId, (int) $order->location_id, (int) $product->id);
                $shortfall = round($quantity - $current, 3);

                $this->stockService->applyMovement(
                    location: $location,
                    product: $product,
                    quantityDelta: $shortfall,
                    reasonCode: RetailStockReasonCode::Adjustment,
                    referenceType: 'retail_order',
                    referenceId: (int) $order->id,
                    note: 'Auto adjustment on oversell (POS sale '.$order->reference.').',
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

        $order->forceFill([
            'status' => RetailOrderStatus::Completed->value,
            'version' => $order->version + 1,
        ])->save();
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

    private function orderLocation(RetailOrder $order): RetailLocation
    {
        /** @var RetailLocation $location */
        $location = RetailLocation::query()
            ->where('company_id', (string) $order->company_id)
            ->findOrFail((int) $order->location_id);

        return $location;
    }

    /**
     * Reference de ticket unique par tenant : POS-YYYYMMDD-XXXXXX
     * (6 caracteres alphanumeriques majuscules, re-tirage en cas de
     * collision — la contrainte unique en base est le filet de securite).
     */
    private function generateReference(string $companyId): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';

            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $reference = 'POS-'.Carbon::now()->format('Ymd').'-'.$suffix;

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
