<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\MarketReviewService;
use App\Modules\Retail\Domain\Models\MarketCustomerAccount;
use App\Modules\Retail\Domain\Models\MarketReview;
use App\Modules\Retail\Domain\Models\RetailProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #7814 — Modération VENDEUR des avis Leopardo Marché.
 *
 * Surface privée (pile tenant `retail.php` : auth Sanctum + tenant +
 * feature flag retail). La table `market_reviews` vit dans le schéma
 * public : chaque requête est bornée PAR VALEUR au tenant courant
 * (`company_id`), et la modération (approve/reject) passe par
 * MarketReviewPolicy (principal/rh — deny-by-default).
 */
class RetailOnlineReviewController extends Controller
{
    public function __construct(private readonly MarketReviewService $reviews) {}

    /**
     * GET /retail/online/reviews — avis de MA boutique/MES produits
     * (filtre `status`, tri récent, pagination ≤ 50).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MarketReview::class);

        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $companyId = (string) $request->user()?->company_id;

        $reviews = MarketReview::query()
            ->where('company_id', $companyId)
            ->when(
                isset($filters['status']),
                static fn ($query) => $query->where('status', (string) $filters['status']),
            )
            ->orderByDesc('id')
            ->paginate(isset($filters['per_page']) ? (int) $filters['per_page'] : 20);

        /** @var list<MarketReview> $items */
        $items = $reviews->items();

        $authors = MarketCustomerAccount::query()
            ->whereIn('id', array_values(array_unique(array_map(
                static fn (MarketReview $review): int => (int) $review->customer_account_id,
                $items,
            ))))
            ->pluck('name', 'id')
            ->all();

        $productIds = array_values(array_filter(array_map(
            static fn (MarketReview $review): ?int => $review->product_id !== null ? (int) $review->product_id : null,
            $items,
        )));

        $productNames = $productIds === []
            ? []
            : RetailProduct::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $productIds)
                ->pluck('name', 'id')
                ->all();

        return response()->json([
            'data' => array_map(
                static fn (MarketReview $review): array => [
                    'id' => (int) $review->id,
                    'target_type' => $review->target_type,
                    'product_id' => $review->product_id !== null ? (int) $review->product_id : null,
                    'product_name' => $review->product_id !== null
                        ? ($productNames[(int) $review->product_id] ?? null)
                        : null,
                    'rating' => (int) $review->rating,
                    'comment' => $review->comment,
                    'status' => $review->status,
                    'author' => $authors[(int) $review->customer_account_id] ?? null,
                    'created_at' => $review->created_at?->toIso8601String(),
                    'moderated_at' => $review->moderated_at?->toIso8601String(),
                ],
                $items,
            ),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }

    /**
     * POST /retail/online/reviews/{review}/approve — publication de l'avis
     * (`pending → approved`, MarketReviewPolicy@moderate).
     */
    public function approve(Request $request, string $review): JsonResponse
    {
        return $this->transition($request, $review, MarketReview::STATUS_APPROVED);
    }

    /**
     * POST /retail/online/reviews/{review}/reject — rejet terminal
     * (`pending → rejected`).
     */
    public function reject(Request $request, string $review): JsonResponse
    {
        return $this->transition($request, $review, MarketReview::STATUS_REJECTED);
    }

    private function transition(Request $request, string $reviewId, string $status): JsonResponse
    {
        $companyId = (string) $request->user()?->company_id;

        $review = ctype_digit($reviewId)
            ? MarketReview::query()
                ->where('company_id', $companyId)
                ->find((int) $reviewId)
            : null;

        if (! $review instanceof MarketReview) {
            // Avis inconnu ou avis d'un AUTRE vendeur : 404 fail-closed.
            abort(404);
        }

        $this->authorize('moderate', $review);

        $updated = $this->reviews->moderate($review, $status);

        return response()->json([
            'data' => [
                'id' => (int) $updated->id,
                'status' => $updated->status,
                'moderated_at' => $updated->moderated_at?->toIso8601String(),
            ],
        ]);
    }
}
