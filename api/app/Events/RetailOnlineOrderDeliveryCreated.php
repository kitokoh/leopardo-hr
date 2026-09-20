<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * #7811 (BC-26 DELIVERY → BC-17 RETAIL) — la livraison BC-26 d'une commande
 * en ligne Leopardo Marché vient d'être créée.
 *
 * Dispatché par le listener Delivery `CreateDeliveryForRetailOnlineOrder`
 * après la création (idempotente) de la livraison. Payload en SCALAIRES
 * uniquement : Retail stocke la référence `DLV-…` sur SA propre table
 * (`retail_orders.delivery_reference`) pour la partager sur la page de suivi
 * publique — jamais d'accès direct aux tables de l'autre BC (registre BC).
 */
class RetailOnlineOrderDeliveryCreated
{
    use Dispatchable;

    public function __construct(
        public string $companyId,
        public int $orderId,
        public string $orderReference,
        public string $deliveryReference,
        public string $deliveryStatus,
    ) {}
}
