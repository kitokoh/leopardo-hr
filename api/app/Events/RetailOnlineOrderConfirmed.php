<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * #7811 (BC-17 RETAIL, epic Leopardo Marché) — une commande en ligne vient
 * d'être CONFIRMÉE par le vendeur (`pending → confirmed`).
 *
 * Dispatché UNIQUEMENT par `RetailOnlineOrderService::confirm()` APRÈS le
 * commit de la transaction (jamais sur une transition rejouée : la machine
 * d'états refuse `confirmed → confirmed`). Payload en SCALAIRES uniquement :
 * l'intégration BC-17 → BC-26 se fait par événement (registre BC, garde
 * module-isolation #5584) — aucun consommateur ne doit importer un modèle
 * Retail.
 *
 * Consommateur connu : le listener Delivery qui crée la livraison BC-26
 * (`source=retail_online`, `source_reference=reference`, anti-doublon par
 * l'unicité (company_id, source, source_reference)).
 */
class RetailOnlineOrderConfirmed
{
    use Dispatchable;

    public function __construct(
        public string $companyId,
        public int $orderId,
        public string $reference,
        public int $totalMinor,
        public string $currency,
        public ?int $codAmountMinor,
        public ?string $customerName,
        public ?string $customerPhone,
        public ?string $deliveryAddress,
        public ?string $deliveryCity,
        public ?string $deliveryNotes,
    ) {}
}
