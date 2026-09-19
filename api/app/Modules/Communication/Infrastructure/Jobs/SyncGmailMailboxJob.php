<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Notifications\Contracts\InAppNotifier;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Exceptions\GmailSyncAuthException;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync Gmail d'UNE boite connectee (BC-29 COMMUNICATION, R2 #7687) —
 * queue dediee `communication` (exigence issue).
 *
 * - Tenant-scoped (`EnsureTenantContext`) : le job s'execute dans le
 *   contexte du tenant proprietaire de la boite (search_path + binding).
 * - Throttling PAR INTEGRATION : `WithoutOverlapping` sur l'id de
 *   l'integration — jamais deux passes concurrentes sur la meme boite ;
 *   les autres boites ne sont pas bloquees.
 * - Backoff sur 429 : `release(Retry-After)` — le job se re-planifie sans
 *   consommer de tentative d'echec agressive contre l'API Gmail.
 * - Token mort (`invalid_grant`, 401) : l'integration est deja `error`
 *   (service R1/sync) -> notification in-app du proprietaire (exigence
 *   issue : « token expire -> status error + notification user »), pas de
 *   retry inutile.
 * - Idempotent : la passe upserte sur les cles Gmail (resync sans doublons),
 *   un rejeu est sur.
 */
final class SyncGmailMailboxJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        private readonly string $companyId,
        private readonly string $integrationId,
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
        return [
            new EnsureTenantContext,
            // Une seule passe a la fois PAR BOITE (throttling par integration).
            (new WithoutOverlapping($this->integrationId))->releaseAfter(60)->expireAfter(600),
        ];
    }

    public function handle(GoogleGmailSyncService $sync, InAppNotifier $notifier): void
    {
        /** @var CommunicationIntegration|null $integration */
        $integration = CommunicationIntegration::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->find($this->integrationId);

        if ($integration === null || ! $integration->isActive()) {
            // Boite revoquee/en erreur entre la planification et l'execution.
            return;
        }

        try {
            $sync->sync($integration);
        } catch (GmailRateLimitedException $exception) {
            // Quota Gmail : backoff en respectant Retry-After — le release ne
            // consomme pas le compteur d'echecs de la meme facon qu'un throw.
            Log::info('communication.gmail.sync_rate_limited', [
                'integration_id' => $integration->id,
                'retry_after' => $exception->retryAfterSeconds,
            ]);

            $this->release($exception->retryAfterSeconds);
        } catch (GmailSyncAuthException $exception) {
            // Integration deja marquee `error` : on notifie le proprietaire
            // (best effort — un echec de notification ne fait pas echouer le job).
            $this->notifyOwner($notifier, $integration, $exception->getMessage());
        }
    }

    private function notifyOwner(
        InAppNotifier $notifier,
        CommunicationIntegration $integration,
        string $errorCode,
    ): void {
        try {
            $notifier->dispatch(
                (int) $integration->employee_id,
                'communication_sync_error',
                __('communication.sync_error_title'),
                __('communication.sync_error_body', [
                    'email' => (string) ($integration->email ?? $integration->provider),
                ]),
                [
                    'integration_id' => $integration->id,
                    'error' => $errorCode,
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('communication.gmail.sync_error_notification_failed', [
                'integration_id' => $integration->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
