<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailBuyerReviewService;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Domain\Enums\MarketplaceReviewStatus;
use App\Modules\Retail\Domain\Models\MarketplaceBuyer;
use App\Modules\Retail\Domain\Models\MarketplaceReview;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreMarketReviewRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avis verifies de la marketplace Leopardo Marche (BC-17 RETAIL, #7814).
 *
 * Routes (`throttle:shop-public`) :
 *   POST /public/market/account/reviews        → soumission (auth buyer,
 *        throttle strict `market-reviews-public` anti-spam) ;
 *   GET  /public/market/products/{id}/reviews  → avis approuves, pagines
 *        (public, 404 fail-closed si produit non eligible).
 *
 * Avis VERIFIE : la commande du buyer doit contenir le produit ET etre
 * `delivered` (RetailBuyerReviewService). DTO public strict : note,
 * commentaire, prenom public du buyer, date — jamais d'email ni de
 * company_id.
 */
class RetailMarketReviewController extends Controller
{
    public function __construct(
        private readonly RetailBuyerReviewService $reviews,
        private readonly RetailMarketplaceService $marketplace,
    ) {}

    /**
     * POST /public/market/account/reviews — soumission d'un avis verifie.
     */
    public function store(StoreMarketReviewRequest $request): JsonResponse
    {
        $buyer = $this->currentBuyer();

        $review = $this->reviews->submit(
            buyer: $buyer,
            orderReference: (string) $request->input('order_reference'),
            productId: (int) $request->input('product_id'),
            rating: (int) $request->input('rating'),
            comment: $request->filled('comment') ? (string) $request->input('comment') : null,
        );

        return response()->json([
            'data' => [
                'product_id' => (int) $review->product_id,
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'status' => $review->status->value,
                'created_at' => $review->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /public/market/products/{id}/reviews — avis approuves, pagines
     * (tri anti-chronologique). 404 fail-closed si le produit n'est pas
     * public (vendeur non opt-in, produit non publie/visible).
     */
    public function index(Request $request, string $id): JsonResponse
    {
        /** @var array<string, mixed> $filters */
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $eligible = $this->marketplace->eligibleCompanyIds();

        $product = ctype_digit($id)
            ? $this->marketplace->findEligibleProduct($eligible, (int) $id)
            : null;

        if (! $product instanceof RetailProduct) {
            abort(404);
        }

        $reviews = MarketplaceReview::query()
            ->where('product_id', (int) $product->id)
            ->where('company_id', (string) $product->company_id)
            ->where('status', MarketplaceReviewStatus::Approved->value)
            ->orderByDesc('id')
            ->paginate(isset($filters['per_page']) ? (int) $filters['per_page'] : 10);

        /** @var list<MarketplaceReview> $items */
        $items = $reviews->items();

        $buyerNames = $this->buyerNamesById($items);

        $rating = $this->marketplace->productRatings([(int) $product->id])[(int) $product->id] ?? null;

        return response()->json([
            'data' => array_map(
                static fn (MarketplaceReview $review): array => [
                    'rating' => (int) $review->rating,
                    'comment' => $review->comment,
                    'buyer_name' => $buyerNames[(int) $review->buyer_id] ?? 'Acheteur',
                    'created_at' => $review->created_at?->toIso8601String(),
                ],
                $items,
            ),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
                'rating_avg' => $rating !== null ? $rating['rating_avg'] : null,
                'rating_count' => $rating !== null ? $rating['rating_count'] : 0,
            ],
        ]);
    }

    /**
     * Nom PUBLIC des auteurs : prenom + initiale du reste (jamais d'email,
     * jamais de nom complet — donnees minimales RGPD).
     *
     * @param  list<MarketplaceReview>  $reviews
     * @return array<int, string>
     */
    private function buyerNamesById(array $reviews): array
    {
        $buyerIds = array_values(array_unique(array_map(
            static fn (MarketplaceReview $review): int => (int) $review->buyer_id,
            $reviews,
        )));

        if ($buyerIds === []) {
            return [];
        }

        $names = [];

        foreach (MarketplaceBuyer::query()->whereIn('id', $buyerIds)->get() as $buyer) {
            $names[(int) $buyer->id] = $this->publicName($buyer->name);
        }

        return $names;
    }

    private function publicName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? '';

        if ($first === '') {
            return 'Acheteur';
        }

        if (count($parts) === 1) {
            return $first;
        }

        return $first.' '.mb_strtoupper(mb_substr((string) $parts[1], 0, 1)).'.';
    }

    /**
     * Buyer authentifie pose par le middleware `market.buyer`.
     */
    private function currentBuyer(): MarketplaceBuyer
    {
        $buyer = app('market_buyer');

        if (! $buyer instanceof MarketplaceBuyer) {
            abort(401, 'UNAUTHENTICATED');
        }

        return $buyer;
    }
}
