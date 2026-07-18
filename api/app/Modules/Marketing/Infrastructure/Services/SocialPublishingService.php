<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountInactiveException;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestration de la publication reseaux sociaux : resout le compte
 * connecte du tenant, appelle l'agregateur (Ayrshare) et met a jour le
 * statut de la publication en consequence. Consomme par CreateSocialPost
 * (publication immediate) et par le futur job PublishScheduledSocialPost
 * (Phase 4) pour les posts planifies devenus dus.
 */
class SocialPublishingService
{
    public function __construct(
        private readonly AyrshareClient $ayrshareClient,
        private readonly SocialAccountRepositoryInterface $socialAccounts,
    ) {}

    /**
     * @throws SocialAccountNotFoundException
     * @throws SocialAccountInactiveException
     */
    public function publishNow(SocialPost $post): SocialPost
    {
        $account = $this->resolveActiveAccount($post->company_id);

        $post->status = SocialPost::STATUS_PUBLISHING;
        $post->save();

        try {
            $result = $this->ayrshareClient->publishPost(
                profileKey: $account->provider_profile_ref,
                content: $post->content,
                platforms: $post->target_platforms,
                mediaUrls: $post->media_paths ?? [],
            );

            $post->status = SocialPost::STATUS_PUBLISHED;
            $post->published_at = Carbon::now();
            $post->provider_post_ref = $result['id'];
            $post->error_message = null;
        } catch (Throwable $e) {
            $post->status = SocialPost::STATUS_FAILED;
            $post->error_message = $e->getMessage();
            $post->attempts++;

            Log::warning('Marketing: social post publish failed', [
                'social_post_id' => $post->id,
                'company_id' => $post->company_id,
                'error' => $e->getMessage(),
            ]);
        }

        $post->save();

        return $post;
    }

    /**
     * @throws SocialAccountNotFoundException
     * @throws SocialAccountInactiveException
     */
    private function resolveActiveAccount(string $companyId): SocialAccount
    {
        $account = $this->socialAccounts->findForCompany($companyId);

        if (! $account) {
            throw SocialAccountNotFoundException::forCompany($companyId);
        }

        if (! $account->isActive()) {
            throw SocialAccountInactiveException::withStatus($account->status);
        }

        return $account;
    }
}
