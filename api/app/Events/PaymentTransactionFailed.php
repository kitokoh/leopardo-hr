<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BC-38 PAIEMENTS (#8712) — une transaction a échoué côté rail.
 *
 * Émis UNIQUEMENT quand aucune écriture comptable n'a été produite : l'argent
 * n'a pas bougé, le ledger ne le dit pas. Un consommateur peut donc traiter
 * cet événement comme « rien à compenser ».
 *
 * Référentiel : docs/architecture/event-catalogue.yaml
 * (`payment.transaction.failed` v1.0.0).
 */
class PaymentTransactionFailed implements TenantScopedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyId,
        public readonly int $transactionId,
        public readonly string $type,
        public readonly string $failureReason,
    ) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }
}
