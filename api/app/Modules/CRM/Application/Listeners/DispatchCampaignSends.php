<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Listeners;

use App\Modules\CRM\Domain\Events\CampaignStarted;
use App\Modules\CRM\Infrastructure\Jobs\ProcessCampaignSendsJob;

/**
 * Prise en charge des envois d'une campagne email démarrée — Issue #7751.
 *
 * Écoute `CampaignStarted` (découplage CRM ↔ canaux, #5724) et met en file
 * le job `ProcessCampaignSendsJob` pour le canal email : les envois
 * `pending` créés au start sont effectivement traités (chunks, respect
 * pause/cancel, statuts sent/failed/suppressed). Les autres canaux
 * (sms/whatsapp) conservent leurs consommateurs propres — aucun couplage.
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
