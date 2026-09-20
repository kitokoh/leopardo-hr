<?php

declare(strict_types=1);

namespace App\Shared\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Confirmation d'une commande en ligne retail (BC-17 RETAIL → BC-26 DELIVERY,
 * issue #7811, spec MARKETPLACE_RETAIL_PUBLIC.md §6 — handoff Delivery).
 *
 * Émis par `RetailOnlineOrderService::confirm()` APRÈS commit de la
 * transaction de confirmation (`pending → confirmed`, jamais d'événement
 * fantôme sur rollback). Écouté par le module Delivery
 * (`CreateRetailOnlineDelivery`) qui crée la livraison BC-26
 * `source = retail_online` de façon idempotente.
 *
 * Placé sous `App\Shared\Events` (et PAS dans `Retail\Domain\Events`) pour
 * que le listener BC-26 ne crée AUCUN import croisé
 * `Modules/Delivery -> Modules/Retail` (règle d'isolation #5584, pattern
 * documenté `App\Shared\Contracts\Crm\EmailContactDirectory`). Payload en
 * SCALAIRES uniquement — aucun modèle Eloquent ne traverse la frontière de
 * bounded context.
 */
final class RetailOnlineOrderConfirmed
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyId,
        public readonly int $orderId,
        public readonly string $reference,
        public readonly int $totalMinor,
        public readonly string $currency,
        public readonly string $customerName,
        public readonly string $customerPhone,
        public readonly string $deliveryAddress,
        public readonly string $deliveryCity,
        public readonly ?string $deliveryNotes,
    ) {}
}
