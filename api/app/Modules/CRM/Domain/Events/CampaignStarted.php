<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Démarrage d'une campagne CRM — Issue #5724.
 *
 * Événement de découplage CRM ↔ canaux (email #5726, WhatsApp #5725,
 * SMS #5727) : les canaux écoutent cet événement pour prendre en charge les
 * envois `pending` de la campagne. Aucun couplage direct inter-modules.
 */
class CampaignStarted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyId,
        public readonly int $campaignId,
        public readonly string $channel,
        public readonly int $audienceSize,
    ) {}
}
