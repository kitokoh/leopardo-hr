<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Retrait d'un consentement CRM — Issue #5722.
 *
 * Dispatched par `CommunicationConsentService::withdraw()` ; écouté par
 * `PropagateConsentRevocation` qui annule les envois de campagne en attente
 * du contact concerné (aucun envoi sans consentement requis).
 */
class CrmConsentRevoked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly int $contactId,
        public readonly string $channel,
        public readonly string $purpose,
    ) {}
}
