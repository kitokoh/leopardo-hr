<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyPurchaseOrderService;

/**
 * Cas d'usage : annulation d'une commande d'achat d'officine — PHARMA-004
 * (#7801).
 *
 * Consommé par `POST /api/v1/pharmacy/purchase-orders/{order}/cancel`
 * (PharmacyPurchaseOrderController::cancel). La Policy `update` reste au
 * niveau interface ; l'Action porte le cas d'usage nommable, la transition
 * d'état reste dans PharmacyPurchaseOrderService (Infrastructure).
 */
class CancelPharmacyPurchaseOrderAction
{
    public function __construct(
        private readonly PharmacyPurchaseOrderService $orders,
    ) {}

    public function execute(PharmacyPurchaseOrder $order): PharmacyPurchaseOrder
    {
        return $this->orders->cancel($order);
    }
}
