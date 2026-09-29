<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Application\Actions;

use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Infrastructure\Services\PharmacyPurchaseOrderService;

/**
 * Cas d'usage : passage d'une commande d'achat de brouillon à envoyée
 * (`draft` → `ordered`) — PHARMA-004 (#7801).
 *
 * Consommé par `POST /api/v1/pharmacy/purchase-orders/{order}/place`
 * (PharmacyPurchaseOrderController::place). La Policy `update` reste au
 * niveau interface ; l'Action porte le cas d'usage nommable, la transition
 * d'état reste dans PharmacyPurchaseOrderService (Infrastructure).
 */
class PlacePharmacyPurchaseOrderAction
{
    public function __construct(
        private readonly PharmacyPurchaseOrderService $orders,
    ) {}

    public function execute(PharmacyPurchaseOrder $order): PharmacyPurchaseOrder
    {
        return $this->orders->place($order);
    }
}
