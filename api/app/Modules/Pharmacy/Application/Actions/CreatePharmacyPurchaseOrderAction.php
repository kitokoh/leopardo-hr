<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyPurchaseOrderService;

/**
 * Cas d'usage : création d'une commande d'achat d'officine (brouillon) —
 * PHARMA-004 (#7801).
 *
 * Consommé par `POST /api/v1/pharmacy/purchase-orders`
 * (PharmacyPurchaseOrderController::store). La résolution tenant du
 * fournisseur (404 si hors tenant) reste au niveau interface ; l'Action
 * porte le cas d'usage nommable. Le numéro `PO-YYYY-XXXX` séquencé par
 * tenant reste calculé dans PharmacyPurchaseOrderService (Infrastructure).
 */
class CreatePharmacyPurchaseOrderAction
{
    public function __construct(
        private readonly PharmacyPurchaseOrderService $orders,
    ) {}

    /**
     * @param  array{supplier_id: int, notes?: string|null, lines: list<array{product_id: int, quantity_ordered: int, unit_price?: string|null}>}  $payload
     */
    public function execute(Employee $actor, PharmacySupplier $supplier, array $payload): PharmacyPurchaseOrder
    {
        return $this->orders->create(
            (string) $actor->company_id,
            $supplier->id,
            array_map(static fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'quantity_ordered' => (int) $line['quantity_ordered'],
                'unit_price' => (string) ($line['unit_price'] ?? '0'),
            ], $payload['lines']),
            $payload['notes'] ?? null,
            $actor->id,
        );
    }
}
