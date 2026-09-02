<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Contracts;

/**
 * Vérification de consentement à l'envoi de campagne — Issue #5724.
 *
 * Contrat consommé par `CampaignService::start()` : chaque contact de
 * l'audience est filtré AVANT création des envois (aucun envoi sans
 * consentement requis). Implémentation par défaut : table `crm_consents`
 * (#5722) — fail-closed si la table est absente.
 */
interface CampaignConsentCheckerInterface
{
    public function allows(int $contactId, string $channel): bool;
}
