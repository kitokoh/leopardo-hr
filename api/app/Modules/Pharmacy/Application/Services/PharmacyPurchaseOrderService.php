<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Services;

use App\Exceptions\DomainException;
use App\Modules\Pharmacy\Domain\Exceptions\PharmacyInvalidTransitionException;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrderLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie des commandes d'achat — PHARMA-004 (#7801).
 *
 * draft → ordered → partially_received → received | cancelled.
 * Numérotation `PO-YYYY-XXXX` séquencée PAR TENANT (verrou pessimiste sur la
 * dernière commande de l'année). La réception délègue chaque ligne à
 * {@see PharmacyStockService::receive()} : lots + mouvements `receipt`,
 * réception partielle supportée, SUR-réception refusée.
 */
class PharmacyPurchaseOrderService
{
    public function __construct(private readonly PharmacyStockService $stock) {}

    /**
     * @param  list<array{product_id: int, quantity_ordered: int, unit_price?: string|numeric}>  $lines
     */
    public function create(string $companyId, int $supplierId, array $lines, ?string $notes = null, ?int $employeeId = null): PharmacyPurchaseOrder
    {
        if ($lines === []) {
            throw new DomainException((string) __('pharmacy.empty_order'), 422, 'PHARMACY_EMPTY_ORDER');
        }

        return DB::transaction(function () use ($companyId, $supplierId, $lines, $notes, $employeeId): PharmacyPurchaseOrder {
            $order = new PharmacyPurchaseOrder([
                'supplier_id' => $supplierId,
                'number' => $this->nextNumber($companyId),
                'status' => 'draft',
                'notes' => $notes,
                'created_by_employee_id' => $employeeId,
            ]);
            $order->company_id = $companyId;
            $order->save();

            foreach ($lines as $line) {
                $orderLine = new PharmacyPurchaseOrderLine([
                    'purchase_order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'quantity_ordered' => $line['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_price' => (string) ($line['unit_price'] ?? '0'),
                ]);
                $orderLine->company_id = $companyId;
                $orderLine->save();
            }

            return $order;
        });
    }

    public function place(PharmacyPurchaseOrder $order): PharmacyPurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw new PharmacyInvalidTransitionException($order->status, 'ordered');
        }

        $order->status = 'ordered';
        $order->ordered_at = Carbon::now();
        $order->save();

        return $order;
    }

    public function cancel(PharmacyPurchaseOrder $order): PharmacyPurchaseOrder
    {
        if (! in_array($order->status, ['draft', 'ordered'], true)) {
            throw new PharmacyInvalidTransitionException($order->status, 'cancelled');
        }

        $order->status = 'cancelled';
        $order->cancelled_at = Carbon::now();
        $order->save();

        return $order;
    }

    /**
     * Réception (partielle ou totale) : chaque ligne reçue crée/incrémente un
     * lot via PharmacyStockService::receive() (mouvement `receipt`).
     *
     * @param  list<array{line_id: int, quantity: int, batch_number: string, expiry_date: string, unit_cost?: string|numeric|null}>  $receivedLines
     */
    public function receive(PharmacyPurchaseOrder $order, array $receivedLines, ?int $employeeId = null): PharmacyPurchaseOrder
    {
        if (! in_array($order->status, ['ordered', 'partially_received'], true)) {
            throw new PharmacyInvalidTransitionException($order->status, 'received');
        }

        if ($receivedLines === []) {
            throw new DomainException((string) __('pharmacy.empty_receipt'), 422, 'PHARMACY_EMPTY_RECEIPT');
        }

        $companyId = (string) $order->company_id;

        return DB::transaction(function () use ($order, $receivedLines, $employeeId, $companyId): PharmacyPurchaseOrder {
            foreach ($receivedLines as $received) {
                if ($received['quantity'] <= 0) {
                    throw new DomainException((string) __('pharmacy.quantity_received_positive'), 422, 'PHARMACY_INVALID_QUANTITY');
                }

                /** @var PharmacyPurchaseOrderLine|null $line */
                $line = PharmacyPurchaseOrderLine::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('purchase_order_id', $order->id)
                    ->whereKey($received['line_id'])
                    ->lockForUpdate()
                    ->first();

                if ($line === null) {
                    throw new DomainException((string) __('pharmacy.order_line_not_found'), 404, 'PHARMACY_ORDER_LINE_NOT_FOUND');
                }

                // Sur-réception refusée : reçu cumulé ≤ commandé.
                if ($line->quantity_received + $received['quantity'] > $line->quantity_ordered) {
                    throw new DomainException(
                        (string) __('pharmacy.over_receipt', [
                            'line' => (int) $line->id,
                            'ordered' => $line->quantity_ordered,
                            'received' => $line->quantity_received,
                            'proposed' => $received['quantity'],
                        ]),
                        422,
                        'PHARMACY_OVER_RECEIPT'
                    );
                }

                $unitCost = $received['unit_cost'] ?? null;

                $this->stock->receive(
                    $companyId,
                    $line->product_id,
                    $received['batch_number'],
                    $received['expiry_date'],
                    $received['quantity'],
                    $unitCost === null ? (string) $line->unit_price : (string) $unitCost,
                    $order->supplier_id,
                    $employeeId,
                    'purchase_order',
                    (int) $order->id,
                );

                $line->quantity_received += $received['quantity'];
                $line->save();
            }

            $fullyReceived = ! PharmacyPurchaseOrderLine::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('purchase_order_id', $order->id)
                ->whereColumn('quantity_received', '<', 'quantity_ordered')
                ->exists();

            $order->status = $fullyReceived ? 'received' : 'partially_received';
            $order->received_at = $fullyReceived ? Carbon::now() : null;
            $order->save();

            return $order;
        });
    }

    /**
     * Numéro `PO-YYYY-XXXX` séquencé par tenant (verrou sur la dernière
     * commande de l'année du tenant — pas de doublon concurrent).
     */
    private function nextNumber(string $companyId): string
    {
        $year = Carbon::now()->format('Y');
        $prefix = 'PO-'.$year.'-';

        /** @var PharmacyPurchaseOrder|null $last */
        $last = PharmacyPurchaseOrder::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->first();

        $sequence = $last === null ? 1 : ((int) substr($last->number, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
