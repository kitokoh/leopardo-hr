<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\CRM\Application\Services\CampaignService;
use App\Modules\CRM\Application\Services\CrmEmailService;
use App\Modules\CRM\Domain\Enums\CampaignSendStatus;
use App\Modules\CRM\Domain\Enums\CampaignStatus;
use App\Modules\CRM\Domain\Exceptions\EmailRateLimitExceededException;
use App\Modules\CRM\Domain\Models\CrmCampaign;
use App\Modules\CRM\Domain\Models\CrmCampaignSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Module CRM — Issue #7751 (envoi effectif des campagnes email).
 *
 * Consomme les `crm_campaign_sends` pending d'une campagne email :
 *   - démarre la campagne si elle est `scheduled` et due (auto-start) ;
 *   - draine les envois pending par petits lots en re-vérifiant le statut
 *     de la campagne entre chaque lot (pause/cancel respectés) ;
 *   - chaque send est réclamé atomiquement (pending → queued) avant envoi —
 *     deux jobs concurrents ne peuvent pas envoyer deux fois le même send ;
 *   - quota horaire atteint (`EmailRateLimitExceededException`) → le job se
 *     re-planifie (release) sans perdre les envois restants ;
 *   - plus aucun pending/queued → la campagne est terminée (auto-finish,
 *     event `CampaignFinished`).
 *
 * Dispatché par le listener `DispatchCampaignSends` (event `CampaignStarted`)
 * et par la commande planifiée `crm:process-campaign-sends` (filet de
 * sécurité + auto-start des campagnes planifiées). Implémente
 * `TenantScopedJob` (même pattern que `PublishScheduledPostJob`).
 */
class ProcessCampaignSendsJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const BATCH_SIZE = 25;

    private const RATE_LIMIT_RELEASE_SECONDS = 900;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(
        public readonly string $companyId,
        public readonly int $campaignId,
    ) {}

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function failed(Throwable $e): void
    {
        Log::error('[ProcessCampaignSendsJob] failed definitively', [
            'campaign_id' => $this->campaignId,
            'company_id' => $this->companyId,
            'error' => $e->getMessage(),
        ]);
    }

    public function handle(CrmEmailService $emails, CampaignService $campaigns): void
    {
        // Le scope tenant courant est établi par EnsureTenantContext : les
        // requêtes "normales" (global scope company_id) suffisent.
        $campaign = CrmCampaign::query()->find($this->campaignId);

        if (! $campaign instanceof CrmCampaign) {
            Log::warning('crm.process_campaign_sends.campaign_not_found', [
                'campaign_id' => $this->campaignId,
            ]);

            return;
        }

        if ($campaign->channel !== 'email') {
            return;
        }

        // Auto-start (#7751) : campagne planifiée devenue due.
        if ($campaign->status === CampaignStatus::Scheduled->value
            && $campaign->scheduled_at !== null
            && $campaign->scheduled_at->isPast()) {
            try {
                $campaign = $campaigns->start($campaign, null);
            } catch (ValidationException $e) {
                Log::warning('crm.process_campaign_sends.auto_start_rejected', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        if ($campaign->status !== CampaignStatus::Running->value) {
            return;
        }

        while (true) {
            $campaign->refresh();

            // Pause/cancel survenus entre deux lots : on s'arrête net.
            if ($campaign->status !== CampaignStatus::Running->value) {
                return;
            }

            $batch = CrmCampaignSend::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', CampaignSendStatus::Pending->value)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            foreach ($batch as $send) {
                // Claim atomique pending → queued : un send n'est jamais
                // traité deux fois, même avec des jobs concurrents.
                $claimed = CrmCampaignSend::query()
                    ->where('id', $send->id)
                    ->where('status', CampaignSendStatus::Pending->value)
                    ->update(['status' => CampaignSendStatus::Queued->value]);

                if ($claimed === 0) {
                    continue;
                }

                $send->refresh();

                try {
                    $emails->sendCampaignSend($send, $campaign->company_id);
                } catch (EmailRateLimitExceededException) {
                    // Quota horaire tenant atteint : on rend le send au pool
                    // et on re-planifie le job — jamais de perte d'envoi.
                    $send->update(['status' => CampaignSendStatus::Pending->value]);
                    $this->release(self::RATE_LIMIT_RELEASE_SECONDS);

                    return;
                } catch (Throwable $e) {
                    $send->update([
                        'status' => CampaignSendStatus::Failed->value,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]);

                    Log::warning('crm.process_campaign_sends.send_failed', [
                        'campaign_id' => $campaign->id,
                        'send_id' => $send->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->finishIfDrained($campaign, $campaigns);
    }

    /**
     * Auto-finish (#7751) : plus aucun envoi pending/queued → campagne
     * terminée (event `CampaignFinished`, report figé).
     */
    private function finishIfDrained(CrmCampaign $campaign, CampaignService $campaigns): void
    {
        $remaining = CrmCampaignSend::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', [CampaignSendStatus::Pending->value, CampaignSendStatus::Queued->value])
            ->exists();

        if ($remaining) {
            return;
        }

        $campaign->refresh();

        if ($campaign->status !== CampaignStatus::Running->value) {
            return;
        }

        try {
            $campaigns->finish($campaign, null);
        } catch (ValidationException $e) {
            Log::warning('crm.process_campaign_sends.auto_finish_rejected', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
