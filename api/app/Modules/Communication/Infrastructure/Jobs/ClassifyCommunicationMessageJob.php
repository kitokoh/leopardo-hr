<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Infrastructure\Services\EmailClassificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Classification IA d'UN message synchronisé (BC-29 COMMUNICATION, R3
 * #7688) — queue dédiée `communication`, dispatché par la sync R2 après
 * ingestion (« messages classés à la sync ») et par l'endpoint de
 * re-classification manuelle (`$force`).
 *
 * - Tenant-scoped (`EnsureTenantContext`, arête MAT-002 BC-29→BC-14 déjà
 *   déclarée pour SyncGmailMailboxJob) ;
 * - Idempotent : un message déjà `classified` n'est pas retraité (sauf
 *   `$force`) ; le service encaisse toute erreur LLM en statut `failed`
 *   (code machine), jamais d'exception qui casserait la file ;
 * - Le LLM est appelé via l'abstraction `LLMClient` (driver `ai.driver`,
 *   fake par défaut hors production #6848) — aucun appel vendeur direct.
 */
final class ClassifyCommunicationMessageJob implements ShouldQueue, TenantScopedJob
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
        private readonly bool $force = false,
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

    public function handle(EmailClassificationService $classifier): void
    {
        /** @var CommunicationMessage|null $message */
        $message = CommunicationMessage::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->find($this->messageId);

        if ($message === null) {
            // Message purgé (révocation R2) entre le dispatch et l'exécution.
            return;
        }

        $classifier->classify($message, $this->force);
    }
}
