<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Billing\Domain\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * #7763 — une facture plateforme vient de transiter vers `paid`.
 *
 * Dispatché UNIQUEMENT par `Invoice::transitionTo()` lors d'une transition
 * RÉELLE vers `paid` (jamais sur la re-transition idempotente paid → paid des
 * webhooks rejoués) : c'est le point unique de la machine à états, commun aux
 * providers Stripe et Chargily.
 */
class InvoicePaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Invoice $invoice) {}
}
