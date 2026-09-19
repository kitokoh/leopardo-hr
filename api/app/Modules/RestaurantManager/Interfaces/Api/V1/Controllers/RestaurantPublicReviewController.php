<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantReviewStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReview;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantPublicBranchResolver;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantPublicReviewRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RESTO-902 (#7747) — Avis clients PUBLICS d'un restaurant par slug.
 *
 * Routes publiques SANS auth (groupe `throttle:shop-public` RESTO-901) :
 *   GET  /public/restaurants/{slug}/reviews → avis PUBLIÉS uniquement, paginés
 *   POST /public/restaurants/{slug}/reviews → soumission (throttle strict dédié
 *        `restaurant-reviews-public` en plus du throttle du groupe)
 *
 * Garde-fous :
 *  - branche résolue fail-closed par `RestaurantPublicBranchResolver` (404) ;
 *  - preuve d'achat : `order_ref` (`RST-…`, non énumérable) d'une commande de
 *    CETTE branche dans un statut terminal servi/livré (served, paid, closed
 *    — après service ; une livraison aboutit à `paid`/`closed`) → 422 sinon ;
 *  - UN SEUL avis par commande (unicité applicative + contrainte unique
 *    (tenant, order_reference)) → 409 en doublon ;
 *  - statut initial `pending` : rien n'est public avant modération gérant ;
 *  - DTO public STRICT en lecture : author_name, rating, comment, date —
 *    jamais d'order_reference (secret du client), d'ID interne ni de PII.
 */
class RestaurantPublicReviewController extends Controller
{
    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly RestaurantPublicBranchResolver $resolver,
    ) {}

    public function index(Request $request, string $slug): JsonResponse
    {
        /** @var array<string, mixed> $filters */
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return $this->resolver->within($slug, function (RestaurantBranch $branch) use ($filters): JsonResponse {
            $perPage = isset($filters['per_page']) && is_numeric($filters['per_page'])
                ? max(1, min(self::MAX_PER_PAGE, (int) $filters['per_page']))
                : 20;

            $reviews = RestaurantReview::query()
                ->where('branch_id', $branch->getAttribute('id'))
                ->where('status', RestaurantReviewStatus::PUBLISHED)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage);

            return response()->json([
                'data' => collect($reviews->items())
                    ->map(fn (RestaurantReview $review): array => [
                        'author_name' => $review->author_name,
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'date' => $review->created_at?->toDateString(),
                    ])
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ]);
        });
    }

    public function store(StoreRestaurantPublicReviewRequest $request, string $slug): JsonResponse
    {
        /** @var array{order_ref: string, rating: int, comment?: string|null, author_name: string} $validated */
        $validated = $request->validated();

        return $this->resolver->within($slug, function (RestaurantBranch $branch) use ($validated): JsonResponse {
            $order = RestaurantOrder::query()
                ->where('branch_id', $branch->getAttribute('id'))
                ->where('reference', $validated['order_ref'])
                ->first();

            if (! $order instanceof RestaurantOrder) {
                abort(404);
            }

            // Statut terminal servi/livré : le service a eu lieu (served),
            // ou la commande est réglée/clôturée (paid/closed — issues du
            // workflow OrderStateMachine après service ou livraison).
            if (! in_array($order->status, [OrderStatus::SERVED, OrderStatus::PAID, OrderStatus::CLOSED], true)) {
                abort(422, __('restaurant.public_reviews.order_not_eligible'));
            }

            if (RestaurantReview::query()->where('order_reference', $order->reference)->exists()) {
                abort(409, __('restaurant.public_reviews.already_reviewed'));
            }

            $review = RestaurantReview::query()->create([
                'company_id' => $order->company_id,
                'branch_id' => (int) $order->getAttribute('branch_id'),
                'order_reference' => $order->reference,
                'rating' => $validated['rating'],
                'comment' => isset($validated['comment']) && trim($validated['comment']) !== ''
                    ? trim($validated['comment'])
                    : null,
                'author_name' => trim($validated['author_name']),
                'status' => RestaurantReviewStatus::PENDING->value,
            ]);

            return response()->json([
                'data' => [
                    'status' => $review->status->value,
                    'rating' => (int) $review->rating,
                    'author_name' => $review->author_name,
                ],
            ], 201);
        });
    }
}
