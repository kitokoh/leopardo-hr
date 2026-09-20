<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Listeners;

use App\Events\RetailOnlineOrderDeliveryCreated;
use App\Modules\Retail\Domain\Models\RetailOrder;

/**
 * #7811 (BC-17 RETAIL) — retour du handoff BC-26 : stocke la référence de
 * la livraison (`DLV-…`) sur LA table du module
 * (`retail_orders.delivery_reference`) pour la partager sur la page de suivi
 * publique (`GET /public/market/orders/{reference}?token=`).
 *
 * Intégration par événement (registre BC) : payload scalaire, aucune lecture
 * des tables Delivery. Idempotent : réécrire la même référence est neutre.
 */
final class StoreRetailOrderDeliveryReference
{
    public function handle(RetailOnlineOrderDeliveryCreated $event): void
    {
        RetailOrder::query()
            ->where('company_id', $event->companyId)
            ->where('id', $event->orderId)
            ->update(['delivery_reference' => $event->deliveryReference]);
    }
}
