<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BC-38 PAIEMENTS (#8712) — le solde déclaré par l'instance fournisseur
 * diffère du solde recalculé depuis le ledger.
 *
 * Émis uniquement quand l'écart est non nul : un rapprochement sain ne
 * produit aucun bruit. Un écart corrigé reste une trace en base
 * (`payment_reconciliations`) ; cet événement est le signal d'alerte.
 *
 * Référentiel : docs/architecture/event-catalogue.yaml
 * (`payment.reconciliation.discrepancy` v1.0.0).
 */
class PaymentReconciliationDiscrepancy implements TenantScopedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyId,
        public readonly string $currency,
        public readonly int $declaredBalance,
        public readonly int $computedBalance,
        public readonly int $difference,
    ) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }
}
