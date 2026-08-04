<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Services\SocialPublishingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Module Marketing — issue #1434.
 *
 * Publie un `social_post` planifie individuel via
 * `SocialPublishingService::publishNow()`. Dispatche par la commande
 * `marketing:publish-scheduled-posts` (voir
 * `App\Console\Commands\PublishScheduledSocialPosts`), un job par post
 * du, au lieu de publier de maniere synchrone dans le processus du
 * scheduler.
 *
 * Implemente `TenantScopedJob` (comme `ProcessSyncQueueJob` /
 * `SendPushNotificationJob`) pour etablir le bon `search_path`
 * PostgreSQL avant execution : indispensable a partir du moment ou une
 * entreprise passe en mode tenancy "schema" (isolation physique), et
 * sans impact aujourd'hui en mode "shared schema" par defaut. La
 * publication effective (appel Ayrshare, mise a jour du statut) reste
 * dans `SocialPublishingService::publishNow()`, deja idempotente
 * (chaque appel repart de l'etat courant du post).
 *
 * File d'attente : `default` (comme la majorite des jobs du projet),
 * deja consommee par le worker Render `leopardo-queue-worker`
 * (`--queue=notifications,emails,pdf,payroll,default`).
 */
class PublishScheduledPostJob implements ShouldQueue, TenantScopedJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    private ?string $resolvedCompanyId = null;

    public function __construct(public readonly int $socialPostId) {}

    public function tenantCompanyId(): ?string
    {
        if ($this->resolvedCompanyId !== null) {
            return $this->resolvedCompanyId;
        }

        $post = SocialPost::query()->withoutGlobalScopes()->find($this->socialPostId);

        return $this->resolvedCompanyId = $post?->company_id;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new EnsureTenantContext];
    }

    public function handle(SocialPublishingService $publishingService): void
    {
        // Le scope tenant courant est deja etabli par EnsureTenantContext,
        // donc une recherche "normale" (avec le global scope company_id)
        // suffit et protege contre toute fuite inter-tenant.
        $post = SocialPost::query()->find($this->socialPostId);

        if (! $post instanceof SocialPost) {
            Log::channel('structured')->warning('marketing.publish_scheduled_post_job.post_not_found', [
                'social_post_id' => $this->socialPostId,
            ]);

            return;
        }

        // Le post peut avoir change d'etat entre la selection par
        // `findDuePosts()` et l'execution effective du job (annule,
        // deja publie manuellement, etc.) — on ne republie que ce qui
        // est toujours planifie et du.
        if (! $post->isDue()) {
            return;
        }

        $publishingService->publishNow($post);
    }
}
