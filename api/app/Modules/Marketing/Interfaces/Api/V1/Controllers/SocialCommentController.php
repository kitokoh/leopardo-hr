<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Domain\Contracts\SocialAccountRepositoryInterface;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Infrastructure\Services\AyrshareClient;
use App\Modules\Marketing\Infrastructure\Services\MarketingAiService;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\ReplyToCommentsRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\SuggestReplyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Interactions sociales — Issue #7754.
 *
 * L'entreprise voit les commentaires reçus sur ses posts publiés et peut y
 * répondre depuis Leopardo (via l'agrégateur Ayrshare, comme la
 * publication). La suggestion IA de réponse est TOUJOURS validée par un
 * humain avant envoi — aucune auto-réponse dans cette tranche.
 *
 * RBAC : même empilement middleware que le reste du module
 * (api.manager:marketing,principal) + policy SocialPostPolicy (view/update).
 * Les commentaires renvoyés par les plateformes sont des données non
 * fiables : ils sont transmis tels quels au client, jamais interprétés.
 */
class SocialCommentController extends Controller
{
    public function __construct(
        private readonly SocialAccountRepositoryInterface $socialAccounts,
        private readonly AyrshareClient $ayrshare,
        private readonly MarketingAiService $ai,
    ) {}

    public function index(Request $request, SocialPost $socialPost): JsonResponse
    {
        $this->authorize('view', $socialPost);

        $account = $this->resolveAccount($request);

        if (! $account instanceof SocialAccount) {
            return $this->noAccountResponse();
        }

        $ref = $this->publishedRef($socialPost);

        if ($ref === null) {
            return $this->notPublishedResponse();
        }

        try {
            $comments = $this->ayrshare->getComments($account->provider_profile_ref, $ref);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => 'SOCIAL_COMMENTS_UNAVAILABLE',
                'message' => $e->getMessage(),
            ], 502);
        }

        return new JsonResponse([
            'data' => [
                'social_post_id' => $socialPost->id,
                'provider_post_ref' => $ref,
                'comments' => $comments,
            ],
        ]);
    }

    public function reply(ReplyToCommentsRequest $request, SocialPost $socialPost): JsonResponse
    {
        $this->authorize('update', $socialPost);

        $account = $this->resolveAccount($request);

        if (! $account instanceof SocialAccount) {
            return $this->noAccountResponse();
        }

        $ref = $this->publishedRef($socialPost);

        if ($ref === null) {
            return $this->notPublishedResponse();
        }

        $platforms = [];
        $requested = $request->input('platforms');
        if (is_array($requested)) {
            foreach ($requested as $platform) {
                if (is_string($platform) && $platform !== '') {
                    $platforms[] = $platform;
                }
            }
        }

        if ($platforms === []) {
            /** @var array<int, string> $targets */
            $targets = $socialPost->target_platforms;
            $platforms = array_values($targets);
        }

        try {
            $result = $this->ayrshare->postComment(
                $account->provider_profile_ref,
                $ref,
                $platforms,
                $request->string('comment')->toString(),
            );
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => 'SOCIAL_COMMENT_REPLY_FAILED',
                'message' => $e->getMessage(),
            ], 502);
        }

        return new JsonResponse(['data' => $result], 201);
    }

    public function suggestReply(SuggestReplyRequest $request, SocialPost $socialPost): JsonResponse
    {
        $this->authorize('view', $socialPost);

        $tone = $request->filled('tone') ? $request->string('tone')->toString() : 'courtois et professionnel';
        $locale = $request->filled('locale') ? $request->string('locale')->toString() : 'fr';
        $platform = $request->filled('platform') ? $request->string('platform')->toString() : 'réseau social';

        try {
            $suggestion = $this->ai->suggestReply(
                $request->string('comment')->toString(),
                $platform,
                $tone,
                $locale,
            );
        } catch (Throwable) {
            return new JsonResponse([
                'error' => 'MARKETING_AI_UNAVAILABLE',
                'message' => 'La suggestion IA est indisponible pour le moment. Réessayez plus tard.',
            ], 503);
        }

        return new JsonResponse([
            'data' => [
                'suggestion' => $suggestion,
                'social_post_id' => $socialPost->id,
            ],
        ]);
    }

    private function resolveAccount(Request $request): ?SocialAccount
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $account = $this->socialAccounts->findForCompany((string) $actor->company_id);

        return $account instanceof SocialAccount && $account->isActive() ? $account : null;
    }

    private function publishedRef(SocialPost $socialPost): ?string
    {
        if ($socialPost->status !== SocialPost::STATUS_PUBLISHED) {
            return null;
        }

        $ref = $socialPost->provider_post_ref;

        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    private function noAccountResponse(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'SOCIAL_ACCOUNT_NOT_FOUND',
            'message' => "Aucun compte social actif connecte pour l'entreprise.",
        ], 404);
    }

    private function notPublishedResponse(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'SOCIAL_POST_NOT_PUBLISHED',
            'message' => 'Les interactions ne sont disponibles que pour un post publié (référence agrégateur requise).',
        ], 409);
    }
}
