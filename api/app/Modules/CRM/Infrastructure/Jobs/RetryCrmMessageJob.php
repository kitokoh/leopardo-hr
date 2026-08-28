<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\CRM\Domain\Models\CrmChannelMessage;
use App\Modules\CRM\Infrastructure\Services\CrmChannelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Retry borné d'un message de canal CRM (issue #5725).
 *
 * Déclenché par CrmChannelService::handleProviderFailure() sur échec
 * retryable (429/5xx). Les tentatives sont plafonnées (max_attempts) ;
 * au-delà, le message est dead-lettered par le service. Jamais de retry
 * infini : le job se termine sans ré-émission si le message n'est plus en
 * statut failed.
 */
final class RetryCrmMessageJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $messageId) {}

    public function tenantCompanyId(): ?string
    {
        $message = CrmChannelMessage::query()->find($this->messageId);

        return $message?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function handle(CrmChannelService $service): void
    {
        try {
            $service->retry($this->messageId);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
