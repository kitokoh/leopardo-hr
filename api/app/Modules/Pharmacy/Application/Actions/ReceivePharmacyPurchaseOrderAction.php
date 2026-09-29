<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyPurchaseOrderService;

/**
 * Cas d'usage : réception (totale ou partielle) d'une commande d'achat —
 * PHARMA-004 (#7801).
 *
 * Consommé par `POST /api/v1/pharmacy/purchase-orders/{order}/receive`
 * (PharmacyPurchaseOrderController::receive). La Policy `update` reste au
 * niveau interface ; l'Action porte le cas d'usage nommable. La création
 * des lots + mouvements `receipt` et le refus de sur-réception restent dans
 * PharmacyPurchaseOrderService (Infrastructure).
 */
class ReceivePharmacyPurchaseOrderAction
{
    public function __construct(
        private readonly PharmacyPurchaseOrderService $orders,
    ) {}

    /**
     * @param  array{lines: list<array{line_id: int, quantity: int, batch_number: string, expiry_date: string, unit_cost?: string|null}>}  $payload
     */
    public function execute(PharmacyPurchaseOrder $order, array $payload, Employee $actor): PharmacyPurchaseOrder
    {
        return $this->orders->receive(
            $order,
            array_map(static fn (array $line): array => [
                'line_id' => (int) $line['line_id'],
                'quantity' => (int) $line['quantity'],
                'batch_number' => (string) $line['batch_number'],
                'expiry_date' => (string) $line['expiry_date'],
                'unit_cost' => $line['unit_cost'] ?? null,
            ], $payload['lines']),
            $actor->id,
        );
    }
}
