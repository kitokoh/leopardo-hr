<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\AuditLog;
use App\Jobs\Middleware\EnsureTenantContext;
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
use Throwable;

/**
 * Traitement des envois `pending` d'une campagne email — Issue #7751.
 *
 * Déclenché par le listener `DispatchCampaignSends` (event `CampaignStarted`)
 * et par la commande planifiée `crm:process-campaign-sends` (drainage).
 *
 * Règles :
 *   - traitement par chunks bornés — le statut de la campagne est relu entre
 *     chaque chunk : une campagne pause/cancel arrête immédiatement le
 *     drainage (les envois restants restent `pending`/`cancelled`) ;
 *   - chaque envoi délègue à `CrmEmailService::sendCampaignSend()` (contenu
 *     de campagne, suppression list, rate limit tenant) → statuts finaux
 *     sent | failed | suppressed ;
 *   - quota tenant épuisé (`EmailRateLimitExceededException`) : arrêt propre,
 *     les envois restent `pending` — la commande planifiée (5 min) reprendra ;
 *   - plus aucun envoi pending et campagne toujours running → auto-finish
 *     (statut `finished`, event `CampaignFinished` via le champ direct — la
 *     transition est auditée ici, module crm) ;
 *   - le run est audité (`campaign.sends_processed`, module crm).
 */
final class ProcessCampaignSendsJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    private const CHUNK_SIZE = 100;

    public function __construct(
        public readonly string $companyId,
        public readonly int $campaignId,
    ) {}

    public function tenantCompanyId(): string
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

    public function handle(CrmEmailService $emailService): void
    {
        $campaign = $this->campaign();

        if ($campaign === null || $campaign->channel !== 'email') {
            return;
        }

        $processed = ['sent' => 0, 'failed' => 0, 'suppressed' => 0];
        $rateLimited = false;

        while ($campaign !== null && $campaign->status === CampaignStatus::Running->value) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, CrmCampaignSend> $chunk */
            $chunk = CrmCampaignSend::query()
                ->withoutGlobalScopes()
                ->where('company_id', $this->companyId)
                ->where('campaign_id', $this->campaignId)
                ->where('status', CampaignSendStatus::Pending->value)
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->get();

            if ($chunk->isEmpty()) {
                break;
            }

            foreach ($chunk as $send) {
                try {
                    $result = $emailService->sendCampaignSend($send, $this->companyId);
                } catch (EmailRateLimitExceededException) {
                    // Quota tenant épuisé : arrêt propre, l'envoi reste
                    // pending — le drainage planifié (5 min) reprendra.
                    $rateLimited = true;
                    break 2;
                } catch (Throwable $e) {
                    report($e);
                    $send->update(['status' => CampaignSendStatus::Failed->value, 'error' => mb_substr($e->getMessage(), 0, 500)]);
                    $processed['failed']++;

                    continue;
                }

                if ($result->messageId !== null) {
                    $processed['sent']++;
                } elseif ($result->status === 'suppressed') {
                    $processed['suppressed']++;
                } else {
                    $processed['failed']++;

                    // Anti-boucle : certains échecs précoces (table contacts
                    // absente…) ne mutent pas l'envoi — le marquer failed ici
                    // pour garantir la progression du drainage.
                    if ($send->refresh()->status === CampaignSendStatus::Pending->value) {
                        $send->update([
                            'status' => CampaignSendStatus::Failed->value,
                            'error' => $result->error !== null ? mb_substr($result->error, 0, 500) : 'send not processed',
                        ]);
                    }
                }
            }

            // Respect pause/cancel : relire le statut entre chaque chunk.
            $campaign = $this->campaign();
        }

        $campaign = $this->campaign();

        if ($campaign === null) {
            return;
        }

        $finished = false;

        if (! $rateLimited
            && $campaign->status === CampaignStatus::Running->value
            && ! $this->hasPendingSends()) {
            $campaign->update([
                'status' => CampaignStatus::Finished->value,
                'finished_at' => now(),
            ]);
            \App\Modules\CRM\Domain\Events\CampaignFinished::dispatch(
                $campaign->company_id,
                $campaign->id,
                CampaignStatus::Finished->value,
            );
            $finished = true;
        }

        if (array_sum($processed) > 0 || $finished) {
            AuditLog::create([
                'company_id' => $this->companyId,
                'user_id' => null,
                'action' => 'campaign.sends_processed',
                'module' => 'crm',
                'auditable_type' => CrmCampaign::class,
                'auditable_id' => $this->campaignId,
                'new_values' => [
                    'sent' => $processed['sent'],
                    'failed' => $processed['failed'],
                    'suppressed' => $processed['suppressed'],
                    'rate_limited' => $rateLimited,
                    'auto_finished' => $finished,
                ],
            ]);
        }
    }

    private function campaign(): ?CrmCampaign
    {
        /** @var CrmCampaign|null $campaign */
        $campaign = CrmCampaign::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('id', $this->campaignId)
            ->first();

        return $campaign;
    }

    private function hasPendingSends(): bool
    {
        return CrmCampaignSend::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('campaign_id', $this->campaignId)
            ->whereIn('status', [CampaignSendStatus::Pending->value, CampaignSendStatus::Queued->value])
            ->exists();
    }
}
