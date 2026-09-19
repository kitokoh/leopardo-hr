<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantReviewStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReview;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicDirectoryCache;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers\Concerns\ScopesRestaurantBranchListings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * RESTO-902 (#7747) — Modération TENANT des avis clients.
 *
 * Routes privées (pile tenant complète, préfixe `/restaurant`) — chemins
 * EXACTS attendus par le front (lot 904) :
 *   GET  /restaurant/reviews?status=          → file de modération
 *   POST /restaurant/reviews/{review}/publish → publication
 *   POST /restaurant/reviews/{review}/reject  → rejet
 *
 * Autorisation : `RestaurantReviewPolicy` (pattern gérant — publication et
 * rejet = geste `manage` de la succursale de l'avis, #7599) ; le listing est
 * scopé par succursales accessibles (ScopesRestaurantBranchListings). Le
 * binding `{review}` porte le scope BelongsToCompany → 404 cross-tenant.
 *
 * Publier ou rejeter change la note moyenne publique : le cache du profil
 * public (RestaurantPublicDirectoryCache, RESTO-901) est invalidé pour le
 * slug de la branche à chaque transition.
 */
class RestaurantReviewModerationController extends Controller
{
    use ScopesRestaurantBranchListings;

    public function __construct(
        private readonly RestaurantPublicDirectoryCache $publicCache,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', RestaurantReview::class)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        /** @var array<string, mixed> $filters */
        $filters = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(RestaurantReviewStatus::values())],
            'branch_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = RestaurantReview::query()->orderByDesc('created_at')->orderByDesc('id');

        if (isset($filters['status']) && is_string($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['branch_id']) && is_numeric($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        $query = $this->scopeToAccessibleBranches($actor, $query);

        $perPage = isset($filters['per_page']) && is_numeric($filters['per_page'])
            ? max(1, min(100, (int) $filters['per_page']))
            : 20;

        $reviews = $query->paginate($perPage);

        return response()->json([
            'data' => collect($reviews->items())
                ->map(fn (RestaurantReview $review): array => $this->moderationPayload($review))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function publish(Request $request, RestaurantReview $review): JsonResponse
    {
        return $this->transition($request, $review, RestaurantReviewStatus::PUBLISHED);
    }

    public function reject(Request $request, RestaurantReview $review): JsonResponse
    {
        return $this->transition($request, $review, RestaurantReviewStatus::REJECTED);
    }

    private function transition(Request $request, RestaurantReview $review, RestaurantReviewStatus $status): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $review->company_id) {
            abort(404);
        }

        if ($actor->cannot('moderate', $review)) {
            abort(403, __('errors.RESOURCE_ACCESS_DENIED'));
        }

        $review->status = $status;
        $review->save();

        // La note moyenne publique (profil par slug, RESTO-901) dépend des
        // avis publiés : purge du cache du profil de la branche.
        /** @var RestaurantBranch|null $branch */
        $branch = RestaurantBranch::query()->find($review->branch_id);
        $this->publicCache->forget($branch?->public_slug);

        return response()->json(['data' => $this->moderationPayload($review)]);
    }

    /**
     * DTO de modération (canal tenant) : l'`order_reference` reste interne
     * au tenant (elle n'est jamais exposée sur le canal public).
     *
     * @return array<string, mixed>
     */
    private function moderationPayload(RestaurantReview $review): array
    {
        return [
            'id' => (int) $review->getAttribute('id'),
            'branch_id' => (int) $review->getAttribute('branch_id'),
            'order_reference' => $review->order_reference,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'author_name' => $review->author_name,
            'status' => $review->status->value,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
