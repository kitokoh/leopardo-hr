<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Listeners;

use App\Modules\CRM\Domain\Events\CampaignStarted;
use App\Modules\CRM\Infrastructure\Jobs\ProcessCampaignSendsJob;

/**
 * Module CRM — Issue #7751.
 *
 * Le canal email prend en charge les envois d'une campagne dès son
 * démarrage : `CampaignStarted` → `ProcessCampaignSendsJob` (queue).
 * Avant #7751 cet événement n'avait aucun listener — une campagne
 * « running » n'envoyait strictement rien.
 *
 * Seul le canal email est pris en charge ici ; sms/whatsapp seront
 * branchés sur leurs adapters (#5725/#5727) dans une tranche dédiée.
 */
final class DispatchCampaignSends
{
    public function handle(CampaignStarted $event): void
    {
        if ($event->channel !== 'email') {
            return;
        }

        ProcessCampaignSendsJob::dispatch($event->companyId, $event->campaignId);
    }
}
