<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelDelivery;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis à la réception d'une livraison FuelStation (FUEL-009, #5803).
 *
 * Contrat Accounting (FUEL-015) : un consommateur outbox publie
 * `fuel.delivery.received.v1` (état figé : quantity_minor, delivered_at,
 * supplier). Notifications (FUEL-019) : alerte stock reçue.
 */
class FuelDeliveryReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelDelivery $delivery,
    ) {}
}
