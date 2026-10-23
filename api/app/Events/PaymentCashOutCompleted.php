<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BC-38 PAIEMENTS (#8712) — un retrait est allé au bout (rail + ledger).
 *
 * Distinct de `payment.transaction.succeeded` par son intention : ici le
 * consommateur sait qu'il s'agit d'une SORTIE de fonds du tenant, et peut
 * rapprocher un solde sans relire le type de la transaction.
 *
 * Référentiel : docs/architecture/event-catalogue.yaml
 * (`payment.cash_out.completed` v1.0.0).
 */
class PaymentCashOutCompleted implements TenantScopedEvent
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
