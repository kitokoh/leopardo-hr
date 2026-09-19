<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\MarketReviewService;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Domain\Models\MarketCustomerAccount;
use App\Modules\Retail\Domain\Models\MarketReview;
use App\Modules\Retail\Domain\Models\RetailProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #7814 — Avis & notations de Leopardo Marché (produit et boutique).
 *
 * Lecture PUBLIQUE (sans auth, `throttle:shop-public`) : uniquement les avis
 * APPROUVÉS (fail-closed), DTO strict — prénom de l'acheteur (jamais
 * l'e-mail), note, commentaire, date. Cible résolue par
 * RetailMarketplaceService (404 fail-closed hors périmètre opt-in).
 *
 * Écriture CONNECTÉE (guard dédié `market_customer`) : avis VÉRIFIÉ
 * post-livraison + un avis par cible et par compte + modération `pending`
 * (MarketReviewService, spec §6).
 */
class MarketReviewController extends Controller
{
    public function __construct(
        private readonly MarketReviewService $reviews,
        private readonly RetailMarketplaceService $marketplace,
    ) {}

    /**
     * GET /public/market/products/{id}/reviews — avis approuvés + note
     * moyenne d'un produit public (404 fail-closed).
     */
    public function productReviews(Request $request, string $id): JsonResponse
    {
        $eligible = $this->marketplace->eligibleCompanyIds();

        $product = ctype_digit($id)
            ? $this->marketplace->findEligibleProduct($eligible, (int) $id)
            : null;

        if (! $product instanceof RetailProduct) {
            abort(404);
        }

        $perPage = $this->perPage($request);
        $reviews = $this->reviews->approvedForProduct((int) $product->id, $perPage);
        $rating = $this->reviews->publicRating(MarketReview::TYPE_PRODUCT, (string) $product->company_id, (int) $product->id);

        return $this->reviewListResponse($reviews, $rating);
    }

    /**
     * GET /public/market/sellers/{sellerSlug}/reviews — avis boutique
     * approuvés + note moyenne (404 fail-closed hors opt-in).
     */
    public function sellerReviews(Request $request, string $sellerSlug): JsonResponse
    {
        $eligible = $this->marketplace->eligibleCompanyIds();
        $company = $this->marketplace->eligibleCompanyForSlug($eligible, $sellerSlug);

        if (! $company instanceof Company) {
            abort(404);
        }

        $perPage = $this->perPage($request);
        $reviews = $this->reviews->approvedForSeller((string) $company->id, $perPage);
        $rating = $this->reviews->publicRating(MarketReview::TYPE_SELLER, (string) $company->id, null);

        return $this->reviewListResponse($reviews, $rating);
    }

    /**
     * POST /public/market/account/reviews — dépôt d'un avis vérifié
     * (compte connecté, cible publique, commande livrée exigée, modération
     * `pending` : l'avis n'est PAS public avant approbation).
     */
    public function store(Request $request): JsonResponse
    {
        $account = $this->authenticated($request);

        $data = $request->validate([
            'target_type' => ['required', 'in:product,seller'],
            'product_id' => ['required_if:target_type,product', 'nullable', 'integer', 'min:1'],
            'seller' => ['required_if:target_type,seller', 'nullable', 'string', 'max:120'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $eligible = $this->marketplace->eligibleCompanyIds();
        $targetType = (string) $data['target_type'];
        $productId = null;

        if ($targetType === MarketReview::TYPE_PRODUCT) {
            $productId = (int) $data['product_id'];
            $product = $this->marketplace->findEligibleProduct($eligible, $productId);

            if (! $product instanceof RetailProduct) {
                abort(404);
            }

            $companyId = (string) $product->company_id;
        } else {
            $company = $this->marketplace->eligibleCompanyForSlug($eligible, (string) $data['seller']);

            if (! $company instanceof Company) {
                abort(404);
            }

            $companyId = (string) $company->id;
        }

        $review = $this->reviews->submit(
            account: $account,
            companyId: $companyId,
            targetType: $targetType,
            productId: $productId,
            rating: (int) $data['rating'],
            comment: isset($data['comment']) ? (string) $data['comment'] : null,
        );

        return response()->json([
            'data' => [
                'id' => (int) $review->id,
                'status' => $review->status,
            ],
        ], 201);
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, MarketReview>  $reviews
     * @param  array{average: float|null, count: int}  $rating
     */
    private function reviewListResponse($reviews, array $rating): JsonResponse
    {
        /** @var list<MarketReview> $items */
        $items = $reviews->items();

        $authors = MarketCustomerAccount::query()
            ->whereIn('id', array_values(array_unique(array_map(
                static fn (MarketReview $review): int => (int) $review->customer_account_id,
                $items,
            ))))
            ->pluck('name', 'id')
            ->all();

        return response()->json([
            'data' => array_map(
                static function (MarketReview $review) use ($authors): array {
                    $name = $authors[(int) $review->customer_account_id] ?? null;

                    return [
                        'id' => (int) $review->id,
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        // Prénom seul : jamais l'e-mail ni le nom complet.
                        'author' => is_string($name) && $name !== ''
                            ? explode(' ', trim($name))[0]
                            : null,
                        'created_at' => $review->created_at?->toIso8601String(),
                    ];
                },
                $items,
            ),
            'rating' => $rating,
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }

    private function perPage(Request $request): int
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return isset($filters['per_page']) ? (int) $filters['per_page'] : 20;
    }

    private function authenticated(Request $request): MarketCustomerAccount
    {
        $account = $request->user('market_customer');

        abort_unless($account instanceof MarketCustomerAccount, 401);

        return $account;
    }
}
