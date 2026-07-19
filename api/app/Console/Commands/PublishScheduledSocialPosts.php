<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Marketing\Domain\Contracts\SocialPostRepositoryInterface;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Services\SocialPublishingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Module Marketing — Phase 3.
 *
 * Publie les social_posts au statut `scheduled` dont l'echeance est
 * passee, tous tenants confondus (meme pattern que
 * AutoCloseAttendanceCommand). SocialPublishingService::publishNow()
 * gere deja le passage a `failed` en cas d'erreur Ayrshare ; cette
 * commande protege en plus contre une exception non prevue au niveau
 * d'un post individuel pour ne jamais interrompre le lot.
 */
class PublishScheduledSocialPosts extends Command
{
    protected $signature = 'marketing:publish-scheduled-posts {--limit=50 : Nombre maximum de posts a traiter par execution}';

    protected $description = 'Publie les publications reseaux sociaux planifiees dont l\'echeance est due';

    public function __construct(
        private readonly SocialPostRepositoryInterface $socialPosts,
        private readonly SocialPublishingService $publishingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $duePosts = $this->socialPosts->findDuePosts($limit);

        $this->info("marketing:publish-scheduled-posts — {$duePosts->count()} post(s) du(s) trouve(s).");

        $published = 0;
        $failed = 0;

        $duePosts->each(function (SocialPost $post) use (&$published, &$failed): void {
            try {
                $result = $this->publishingService->publishNow($post);

                if ($result->status === SocialPost::STATUS_PUBLISHED) {
                    $published++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $failed++;

                Log::error('marketing:publish-scheduled-posts — echec inattendu', [
                    'social_post_id' => $post->id,
                    'company_id' => $post->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->info("Publies: {$published} — Echecs: {$failed}.");

        Log::info('marketing:publish-scheduled-posts run complete', [
            'due' => $duePosts->count(),
            'published' => $published,
            'failed' => $failed,
        ]);

        return self::SUCCESS;
    }
}
