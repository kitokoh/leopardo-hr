<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Exceptions\GmailSyncAuthException;
use App\Modules\Communication\Domain\Models\CommunicationFollowUp;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpLog;
use App\Modules\Communication\Infrastructure\Services\CommunicationFollowUpGuard;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailFollowUpSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envoi d'UNE relance automatique (BC-29 COMMUNICATION, R4 #7689) — queue
 * dediee `communication`, dispatche par la commande schedulee
 * `communication:send-follow-ups`.
 *
 * - Tenant-scoped (`EnsureTenantContext`) + `WithoutOverlapping` sur la
 *   BOITE : les envois d'une meme boite sont serialises (les plafonds
 *   journaliers sont donc evalues sans course).
 * - TOUS les garde-fous (`CommunicationFollowUpGuard`) sont re-evalues ICI,
 *   juste avant l'envoi : reponse detectee via la sync R2 -> `cancelled` ;
 *   opt-out / consentement CRM / liste / scope manquant -> `skipped` ;
 *   quiet hours / plafonds -> l'echeance RESTE `pending` (rejouee a la
 *   passe suivante, aucune trace terminale).
 * - Toute decision TERMINALE est auditee (`communication_follow_up_logs`,
 *   exigence issue : « tout envoi audite »).
 * - 429 Gmail -> release(Retry-After) ; token mort -> echeance `failed` +
 *   audit (l'integration est deja marquee `error` par le sender R1).
 */
final class SendCommunicationFollowUpJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        private readonly string $companyId,
        private readonly string $followUpId,
    ) {
        $this->onQueue('communication');
    }

    public function tenantCompanyId(): string
    {
        return $this->companyId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(CommunicationFollowUpGuard $guard, GoogleGmailFollowUpSender $sender): void
    {
        /** @var CommunicationFollowUp|null $followUp */
        $followUp = CommunicationFollowUp::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->find($this->followUpId);

        if ($followUp === null || $followUp->status !== CommunicationFollowUp::STATUS_PENDING) {
            // Echeance purgee, annulee ou deja traitee (dedup) : rien a faire.
            return;
        }

        $decision = $guard->evaluate($followUp);

        if ($decision['verdict'] === CommunicationFollowUpGuard::VERDICT_DEFER) {
            // Temporel (quiet hours, plafonds) : reste `pending`, la
            // prochaine passe schedulee la reprendra.
            return;
        }

        if ($decision['verdict'] === CommunicationFollowUpGuard::VERDICT_CANCEL) {
            $followUp->forceFill([
                'status' => CommunicationFollowUp::STATUS_CANCELLED,
                'skip_reason' => $decision['reason'],
            ])->save();

            CommunicationFollowUpLog::record($followUp, CommunicationFollowUpLog::ACTION_CANCELLED, $decision['reason']);

            return;
        }

        if ($decision['verdict'] === CommunicationFollowUpGuard::VERDICT_SKIP) {
            $followUp->forceFill([
                'status' => CommunicationFollowUp::STATUS_SKIPPED,
                'skip_reason' => $decision['reason'],
            ])->save();

            CommunicationFollowUpLog::record($followUp, CommunicationFollowUpLog::ACTION_SKIPPED, $decision['reason']);

            return;
        }

        $templateKey = $this->templateKeyFor($followUp);

        try {
            $sentGmailId = $sender->send($followUp, $templateKey);
        } catch (GmailRateLimitedException $exception) {
            Log::info('communication.follow_up.rate_limited', [
                'follow_up_id' => $followUp->id,
                'retry_after' => $exception->retryAfterSeconds,
            ]);

            $this->release($exception->retryAfterSeconds);

            return;
        } catch (GmailSyncAuthException $exception) {
            $followUp->forceFill([
                'status' => CommunicationFollowUp::STATUS_FAILED,
                'skip_reason' => mb_substr($exception->getMessage(), 0, 64),
            ])->save();

            CommunicationFollowUpLog::record(
                $followUp,
                CommunicationFollowUpLog::ACTION_FAILED,
                mb_substr($exception->getMessage(), 0, 64)
            );

            return;
        }

        if ($sentGmailId === null) {
            $followUp->forceFill([
                'status' => CommunicationFollowUp::STATUS_FAILED,
                'skip_reason' => 'send_failed',
            ])->save();

            CommunicationFollowUpLog::record($followUp, CommunicationFollowUpLog::ACTION_FAILED, 'send_failed');

            return;
        }

        $followUp->forceFill([
            'status' => CommunicationFollowUp::STATUS_SENT,
            'sent_at' => now(),
            'sent_gmail_message_id' => $sentGmailId,
            'skip_reason' => null,
        ])->save();

        CommunicationFollowUpLog::record($followUp, CommunicationFollowUpLog::ACTION_SENT);
    }

    private function templateKeyFor(CommunicationFollowUp $followUp): string
    {
        $step = $followUp->rule?->steps
            ->firstWhere('position', $followUp->step_position);

        return $step->template_key ?? 'communication_follow_up';
    }
}
