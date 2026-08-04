<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Marketing\Domain\Contracts\SocialPostRepositoryInterface;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Jobs\PublishScheduledPostJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Module Marketing — Phase 3 / issue #1434.
 *
 * Selectionne les social_posts au statut `scheduled` dont l'echeance
 * est passee, tous tenants confondus (meme pattern que
 * AutoCloseAttendanceCommand), et dispatche un `PublishScheduledPostJob`
 * par post du sur la file `default`. Le job etablit lui-meme le bon
 * contexte tenant (via `EnsureTenantContext`) avant d'appeler
 * `SocialPublishingService::publishNow()`, qui gere deja le passage a
 * `failed` en cas d'erreur Ayrshare ; cette commande protege en plus
 * contre une exception non prevue au moment du dispatch pour ne jamais
 * interrompre le lot.
 */
class PublishScheduledSocialPosts extends Command
{
    protected $signature = 'marketing:publish-scheduled-posts {--limit=50 : Nombre maximum de posts a traiter par execution}';

    protected $description = 'Publie les publications reseaux sociaux planifiees dont l\'echeance est due';

    public function __construct(
        private readonly SocialPostRepositoryInterface $socialPosts,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $duePosts = $this->socialPosts->findDuePosts($limit);

        $this->info("marketing:publish-scheduled-posts — {$duePosts->count()} post(s) du(s) trouve(s).");

        $dispatched = 0;
        $failed = 0;

        $duePosts->each(function (SocialPost $post) use (&$dispatched, &$failed): void {
            try {
                PublishScheduledPostJob::dispatch($post->id);
                $dispatched++;
            } catch (Throwable $e) {
                $failed++;

                Log::error('marketing:publish-scheduled-posts — echec de dispatch', [
                    'social_post_id' => $post->id,
                    'company_id' => $post->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->info("Dispatches: {$dispatched} — Echecs: {$failed}.");

        Log::info('marketing:publish-scheduled-posts run complete', [
            'due' => $duePosts->count(),
            'dispatched' => $dispatched,
            'failed' => $failed,
        ]);

        return self::SUCCESS;
    }
}
