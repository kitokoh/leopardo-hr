<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * TRAVEL-806 (#6097) — Cycle de vie d'une livraison de webhook.
 */
enum TravelWebhookDeliveryStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case DEAD = 'dead';
}
