<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Exceptions\GmailSyncAuthException;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Infrastructure\Services\CommunicationReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Preparation de la reponse assistee d'UN message classifie (BC-29
 * COMMUNICATION, R5 #7690 — spec §3.5) — queue dediee `communication`,
 * dispatche par `ClassifyCommunicationMessageJob` apres classification
 * reussie (le pipeline R3 pose `ai_category`, la politique R5 decide).
 *
 * - Tenant-scoped (`EnsureTenantContext`, meme arete que la sync R2) ;
 * - Idempotent : UNIQUE (company, message) dans la file Pending — un
 *   message ne produit qu'une proposition, meme rejoue ;
 * - 429 Gmail -> release(Retry-After) ; token mort -> log code machine
 *   (l'integration est deja marquee `error` par le sender, pattern R4) ;
 * - Le LLM est appele via l'abstraction `LLMClient` (driver `ai.driver`,
 *   fake par defaut hors production #6848) — aucun appel vendeur direct.
 */
final class PrepareCommunicationReplyJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        private readonly string $companyId,
        private readonly string $messageId,
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

    public function handle(CommunicationReplyService $replies): void
    {
        /** @var CommunicationMessage|null $message */
        $message = CommunicationMessage::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->find($this->messageId);

        if ($message === null) {
            // Message purge (revocation R2) entre le dispatch et l'execution.
            return;
        }

        try {
            $replies->prepare($message);
        } catch (GmailRateLimitedException $exception) {
            $this->release($exception->retryAfterSeconds);
        } catch (GmailSyncAuthException $exception) {
            // Integration deja marquee `error` par le sender : code machine
            // en log, la boite doit etre reconnectee (pattern R2/R4).
            Log::warning('communication.reply.auth_failed', [
                'message_id' => $this->messageId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
