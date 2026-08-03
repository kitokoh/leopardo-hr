<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Infrastructure\Services;

use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountInactiveException;
use App\Modules\Marketing\Domain\Exceptions\SocialAccountNotFoundException;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Services\Publishers\SocialPublisherResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Orchestration de la publication reseaux sociaux : resout le compte
 * connecte du tenant, valide/route la publication vers le
 * `SocialPublisherInterface` (LinkedIn/Meta/Twitter, voir issue #1433)
 * responsable des plateformes ciblees, puis met a jour le statut du post
 * en consequence. Consomme par CreateSocialPost (publication immediate)
 * et par PublishScheduledSocialPosts (Phase 4) pour les posts planifies
 * devenus dus.
 */
class SocialPublishingService
{
    public function __construct(
        private readonly SocialAccountRepositoryInterface $socialAccounts,
        private readonly SocialPublisherResolver $publisherResolver,
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
            $result = $this->publishToTargetPlatforms($post, $account);

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
     * Regroupe les `target_platforms` du post par `SocialPublisherInterface`
     * (issue #1433) et publie chaque groupe. Ayrshare traite plusieurs
     * plateformes en un seul appel par publisher, donc la reponse
     * retournee est celle du dernier groupe publie (identifiant Ayrshare
     * du post commun a toutes les plateformes de ce groupe) — suffisant
     * pour la tracabilite `provider_post_ref` d'un post mono-publisher,
     * le cas Phase 3/4 actuel.
     *
     * @return array{id: string, status: string, raw: array<string, mixed>}
     */
    private function publishToTargetPlatforms(SocialPost $post, SocialAccount $account): array
    {
        $groups = $this->publisherResolver->groupByPublisher($post->target_platforms);

        $result = null;

        foreach ($groups as $group) {
            $result = $group['publisher']->publish(
                profileKey: $account->provider_profile_ref,
                content: $post->content,
                platforms: $group['platforms'],
                mediaUrls: $post->media_paths ?? [],
            );
        }

        if ($result === null) {
            throw new RuntimeException('Marketing: aucune plateforme cible a publier.');
        }

        return $result;
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
