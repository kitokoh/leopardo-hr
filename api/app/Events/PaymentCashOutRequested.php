<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BC-38 PAIEMENTS (#8712) — une demande de retrait a été enregistrée.
 *
 * Émis à la CRÉATION de la transaction, avant tout appel au rail : un
 * consommateur peut donc notifier l'utilisateur « retrait en cours » même si
 * le rail échoue ensuite (`payment.transaction.failed` suit).
 *
 * Référentiel : docs/architecture/event-catalogue.yaml
 * (`payment.cash_out.requested` v1.0.0).
 */
class PaymentCashOutRequested implements TenantScopedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyId,
        public readonly int $transactionId,
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }
}
