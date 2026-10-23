<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Tenant\Domain\Contracts\TenantScopedEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * BC-38 PAIEMENTS (#8712) — une transaction a abouti.
 *
 * Contrat cross-BC : un consommateur (Paie, Cagnottes, VTC, Boutique) réagit
 * à une transaction réussie SANS importer les modèles de Paiements — c'est le
 * seul mécanisme d'intégration autorisé (même règle que `catalog.inquiry_received`).
 *
 * Aucune PII dans le payload : identifiants et montants uniquement.
 *
 * Référentiel : docs/architecture/event-catalogue.yaml
 * (`payment.transaction.succeeded` v1.0.0).
 */
class PaymentTransactionSucceeded implements TenantScopedEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $companyId,
        public readonly int $transactionId,
        public readonly string $type,
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }
}
