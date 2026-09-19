<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Services;

use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrderLine;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * PHARMA-004 (#7801) — cycle de vie des commandes d'achat de l'officine.
 *
 * draft → ordered → partially_received → received | cancelled. Toutes les
 * opérations sont TRANSACTIONNELLES ; les transitions invalides sont
 * refusées (422). La réception délègue chaque ligne à
 * PharmacyStockService::receive() — lots + mouvements `receipt` (#7800),
 * traçabilité complète — et refuse la sur-réception (quantité reçue jamais
 * supérieure à la commandée). Numéro `PO-YYYY-XXXX` séquencé PAR TENANT en
 * transaction (verrou sur les commandes de l'année ; l'unique
 * (company_id, number) est le filet de sécurité en cas de course).
 *
 * Pas de facade Laravel ici (pureté de couche Application, garde #6568) :
 * la connexion est injectée via ConnectionInterface.
 */
final class PharmacyPurchasingService
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly PharmacyStockService $stockService,
    ) {}

    /**
     * Crée une commande `draft` avec ses lignes (produits du tenant).
     *
     * @param  list<array{product_id: int, quantity_ordered: int, unit_price?: string|null}>  $lines
     *
     * @throws ValidationException 422 (fournisseur ou produit hors tenant, panier vide).
     */
    public function create(
        PharmacySupplier $supplier,
        array $lines,
        ?string $notes = null,
        ?int $employeeId = null,
    ): PharmacyPurchaseOrder {
        $companyId = (string) $supplier->company_id;

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Une commande doit porter au moins une ligne.',
            ]);
        }

        /** @var PharmacyPurchaseOrder $order */
        $order = $this->connection->transaction(
            function () use ($supplier, $companyId, $lines, $notes, $employeeId): PharmacyPurchaseOrder {
                $order = PharmacyPurchaseOrder::query()->create([
                    'company_id' => $companyId,
                    'supplier_id' => (int) $supplier->id,
                    'number' => $this->nextNumber($companyId),
                    'status' => PharmacyPurchaseOrder::STATUS_DRAFT,
                    'notes' => $notes,
                    'created_by_employee_id' => $employeeId,
                ]);

                foreach ($lines as $index => $line) {
                    /** @var PharmacyProduct|null $product */
                    $product = PharmacyProduct::query()
                        ->where('company_id', $companyId)
                        ->find($line['product_id']);

                    if (! $product instanceof PharmacyProduct) {
                        throw ValidationException::withMessages([
                            'lines.'.$index.'.product_id' => 'Produit introuvable dans ce tenant.',
                        ]);
                    }

                    PharmacyPurchaseOrderLine::query()->create([
                        'company_id' => $companyId,
                        'purchase_order_id' => (int) $order->id,
                        'product_id' => (int) $product->id,
                        'quantity_ordered' => $line['quantity_ordered'],
                        'quantity_received' => 0,
                        'unit_price' => $line['unit_price'] ?? $product->purchase_price,
                    ]);
                }

                return $order;
            }
        );

        return $order;
    }

    /**
     * Passe une commande `draft` à `ordered` (elle part chez le fournisseur).
     *
     * @throws ValidationException 422 si la commande n'est pas en brouillon.
     */
    public function markOrdered(PharmacyPurchaseOrder $order): PharmacyPurchaseOrder
    {
        /** @var PharmacyPurchaseOrder $updated */
        $updated = $this->connection->transaction(function () use ($order): PharmacyPurchaseOrder {
            $locked = $this->lockedOrder($order);

            if ($locked->status !== PharmacyPurchaseOrder::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Seule une commande en brouillon peut etre passee.',
                ]);
            }

            $locked->forceFill([
                'status' => PharmacyPurchaseOrder::STATUS_ORDERED,
                'ordered_at' => Carbon::now(),
            ])->save();

            return $locked->refresh();
        });

        return $updated;
    }

    /**
     * Annule une commande non réceptionnée (draft ou ordered uniquement —
     * une commande partiellement ou totalement reçue a déjà produit des
     * lots : elle ne peut plus être annulée).
     *
     * @throws ValidationException 422 sur transition invalide.
     */
    public function cancel(PharmacyPurchaseOrder $order): PharmacyPurchaseOrder
    {
        /** @var PharmacyPurchaseOrder $updated */
        $updated = $this->connection->transaction(function () use ($order): PharmacyPurchaseOrder {
            $locked = $this->lockedOrder($order);

            if (! in_array($locked->status, [PharmacyPurchaseOrder::STATUS_DRAFT, PharmacyPurchaseOrder::STATUS_ORDERED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Seule une commande brouillon ou passee peut etre annulee.',
                ]);
            }

            $locked->forceFill(['status' => PharmacyPurchaseOrder::STATUS_CANCELLED])->save();

            return $locked->refresh();
        });

        return $updated;
    }

    /**
     * Réceptionne des lignes d'une commande `ordered` ou
     * `partially_received` : chaque ligne reçue (quantité, n° de lot,
     * péremption, coût optionnel) crée/incrémente un lot + mouvement
     * `receipt` via PharmacyStockService. Réception partielle supportée ;
     * sur-réception refusée. Statut recalculé : `received` quand toutes les
     * lignes sont complètes, `partially_received` sinon.
     *
     * @param  list<array{line_id: int, quantity: int, batch_number: string, expiry_date: string, unit_cost?: string|null}>  $receipts
     *
     * @throws ValidationException 422 (transition invalide, ligne inconnue, sur-réception).
     */
    public function receive(PharmacyPurchaseOrder $order, array $receipts, ?int $employeeId = null): PharmacyPurchaseOrder
    {
        if ($receipts === []) {
            throw ValidationException::withMessages([
                'receipts' => 'Aucune ligne de reception fournie.',
            ]);
        }

        /** @var PharmacyPurchaseOrder $updated */
        $updated = $this->connection->transaction(function () use ($order, $receipts, $employeeId): PharmacyPurchaseOrder {
            $locked = $this->lockedOrder($order);
            $companyId = (string) $locked->company_id;

            if (! in_array($locked->status, [PharmacyPurchaseOrder::STATUS_ORDERED, PharmacyPurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Seule une commande passee (ou partiellement recue) peut etre receptionnee.',
                ]);
            }

            foreach ($receipts as $index => $receipt) {
                /** @var PharmacyPurchaseOrderLine|null $line */
                $line = PharmacyPurchaseOrderLine::query()
                    ->where('company_id', $companyId)
                    ->where('purchase_order_id', (int) $locked->id)
                    ->lockForUpdate()
                    ->find($receipt['line_id']);

                if (! $line instanceof PharmacyPurchaseOrderLine) {
                    throw ValidationException::withMessages([
                        'receipts.'.$index.'.line_id' => 'Ligne introuvable sur cette commande.',
                    ]);
                }

                $quantity = $receipt['quantity'];

                if ($quantity <= 0 || $line->quantity_received + $quantity > $line->quantity_ordered) {
                    throw ValidationException::withMessages([
                        'receipts.'.$index.'.quantity' => 'Sur-reception refusee : la quantite recue depasserait la quantite commandee.',
                    ]);
                }

                /** @var PharmacyProduct $product */
                $product = PharmacyProduct::query()
                    ->where('company_id', $companyId)
                    ->findOrFail($line->product_id);

                $this->stockService->receive(
                    product: $product,
                    batchNumber: $receipt['batch_number'],
                    expiryDate: Carbon::parse($receipt['expiry_date']),
                    quantity: $quantity,
                    unitCost: $receipt['unit_cost'] ?? (string) $line->unit_price,
                    supplierId: $locked->supplier_id,
                    referenceType: 'pharmacy_purchase_order',
                    referenceId: (int) $locked->id,
                    employeeId: $employeeId,
                );

                $line->forceFill(['quantity_received' => $line->quantity_received + $quantity])->save();
            }

            $complete = ! PharmacyPurchaseOrderLine::query()
                ->where('company_id', $companyId)
                ->where('purchase_order_id', (int) $locked->id)
                ->whereColumn('quantity_received', '<', 'quantity_ordered')
                ->exists();

            $locked->forceFill([
                'status' => $complete ? PharmacyPurchaseOrder::STATUS_RECEIVED : PharmacyPurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                'received_at' => $complete ? Carbon::now() : $locked->received_at,
            ])->save();

            return $locked->refresh();
        });

        return $updated;
    }

    /**
     * Numéro `PO-YYYY-XXXX` séquencé par tenant — à appeler DANS une
     * transaction (verrou sur les commandes du tenant pour l'année).
     */
    private function nextNumber(string $companyId): string
    {
        $year = Carbon::now()->year;
        $prefix = sprintf('PO-%d-', $year);

        /** @var string|null $last */
        $last = PharmacyPurchaseOrder::query()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return sprintf('%s%04d', $prefix, $next);
    }

    private function lockedOrder(PharmacyPurchaseOrder $order): PharmacyPurchaseOrder
    {
        /** @var PharmacyPurchaseOrder $locked */
        $locked = PharmacyPurchaseOrder::query()
            ->where('company_id', (string) $order->company_id)
            ->lockForUpdate()
            ->findOrFail((int) $order->id);

        return $locked;
    }
}
