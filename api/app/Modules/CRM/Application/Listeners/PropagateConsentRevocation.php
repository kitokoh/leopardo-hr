<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Listeners;

use App\Modules\CRM\Domain\Events\CrmConsentRevoked;
use App\Modules\CRM\Infrastructure\Services\CampaignConsentRevocationHandler;

/**
 * Propagation du retrait de consentement — Issue #5722.
 *
 * Écoute `CrmConsentRevoked` et délègue l'annulation des envois de campagne
 * en attente au handler dédié (module CRM, couche Application). Le handler
 * est un no-op tant que la table `crm_campaign_sends` n'existe pas (livrée
 * par l'issue #5724) — aucun crash en attendant, aucune fuite d'envoi une
 * fois la table présente.
 */
final class PropagateConsentRevocation
{
    public function __construct(private readonly CampaignConsentRevocationHandler $handler) {}

    public function handle(CrmConsentRevoked $event): void
    {
        $this->handler->cancelPendingSends(
            $event->companyId,
            $event->contactId,
            $event->channel,
            $event->purpose,
        );
    }
}
