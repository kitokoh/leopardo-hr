<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Application\Actions;

use App\Modules\Marketing\Domain\Exceptions\SocialAccountInactiveException;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Services\SocialPublishingService;
use Illuminate\Support\Carbon;

/**
 * Planifie une publication existante (statut `draft`) ou, si aucune date
 * n'est fournie, la publie immediatement. C'est le point d'entree commun
 * pour :
 *  - l'editeur multi-reseaux web (calendrier -> planification differee)
 *  - le cas d'usage terrain mobile (photo evenement -> $when=null -> immediat)
 */
class SchedulePost
{
    public function __construct(
        private readonly SocialPublishingService $publishingService,
    ) {}

    /**
     * @throws SocialAccountNotFoundException
     * @throws SocialAccountInactiveException
     */
    public function execute(SocialPost $post, ?Carbon $when = null): SocialPost
    {
        if ($when !== null && $when->isFuture()) {
            $post->status = SocialPost::STATUS_SCHEDULED;
            $post->scheduled_at = $when;
            $post->save();

            return $post;
        }

        return $this->publishingService->publishNow($post);
    }
}
