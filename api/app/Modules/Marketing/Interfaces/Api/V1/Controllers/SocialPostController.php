<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Marketing\Application\Actions\CreateSocialPost;
use App\Modules\Marketing\Application\Actions\SchedulePost;
use App\Modules\Marketing\Application\DTOs\CreateSocialPostDTO;
use App\Modules\Marketing\Domain\Contracts\SocialPostRepositoryInterface;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\SchedulePostRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\StoreSocialPostRequest;
use App\Modules\Marketing\Interfaces\Api\V1\Requests\UpdateSocialPostRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Module Marketing — Phase 3.
 *
 * CRUD + planification/publication des posts reseaux sociaux d'un
 * tenant. `SocialPost` est scope au tenant courant via le trait
 * BelongsToCompany (global scope), donc le binding de route
 * {socialPost} echoue deja en 404 pour un post d'une autre entreprise.
 */
class SocialPostController extends Controller
{
    public function __construct(
        private readonly SocialPostRepositoryInterface $socialPosts,
        private readonly CreateSocialPost $createSocialPost,
        private readonly SchedulePost $schedulePost,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SocialPost::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $perPage = min(100, max(1, $request->integer('per_page', 15)));

        return new JsonResponse(
            $this->socialPosts->paginateByCompany($actor->company_id, $perPage)
        );
    }

    public function store(StoreSocialPostRequest $request): JsonResponse
    {
        $this->authorize('create', SocialPost::class);

        /** @var Employee $actor */
        $actor = $request->user();

        $dto = CreateSocialPostDTO::fromArray([
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'content' => $request->validated('content'),
            'target_platforms' => $request->validated('target_platforms'),
            'media_paths' => $request->validated('media_paths'),
        ]);

        $post = $this->createSocialPost->execute($dto);

        $scheduledAt = $request->validated('scheduled_at');
        if ($scheduledAt !== null) {
            $post = $this->schedulePost->execute($post, Carbon::parse($scheduledAt));
        }

        return new JsonResponse(['data' => $post], 201);
    }

    public function show(SocialPost $socialPost): JsonResponse
    {
        $this->authorize('view', $socialPost);

        return new JsonResponse(['data' => $socialPost]);
    }

    public function update(UpdateSocialPostRequest $request, SocialPost $socialPost): JsonResponse
    {
        $this->authorize('update', $socialPost);

        $socialPost->update($request->validated());

        return new JsonResponse(['data' => $socialPost->fresh()]);
    }

    public function destroy(SocialPost $socialPost): JsonResponse
    {
        $this->authorize('delete', $socialPost);

        $this->socialPosts->delete($socialPost);

        return new JsonResponse(null, 204);
    }

    public function publish(SchedulePostRequest $request, SocialPost $socialPost): JsonResponse
    {
        $this->authorize('publish', $socialPost);

        $scheduledAt = $request->validated('scheduled_at');
        $when = $scheduledAt !== null ? Carbon::parse($scheduledAt) : null;

        $post = $this->schedulePost->execute($socialPost, $when);

        return new JsonResponse(['data' => $post]);
    }
}
