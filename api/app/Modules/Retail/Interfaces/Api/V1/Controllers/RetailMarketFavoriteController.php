<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Domain\Models\MarketplaceBuyer;
use App\Modules\Retail\Domain\Models\MarketplaceFavorite;
use App\Modules\Retail\Domain\Models\RetailCategory;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Interfaces\Api\V1\Requests\StoreMarketFavoriteRequest;
use Illuminate\Http\JsonResponse;

/**
 * Favoris des acheteurs marketplace (BC-17 RETAIL, #7814).
 *
 * Routes `/api/v1/public/market/account/favorites` (auth buyer,
 * `throttle:shop-public`) :
 *   GET    /account/favorites               → liste (DTO produits publics)
 *   POST   /account/favorites               → ajout (produit public eligible)
 *   DELETE /account/favorites/{productId}   → retrait (idempotent)
 *
 * La reference interne (company_id vendeur) est resolue cote serveur au
 * moment de l'ajout et n'est JAMAIS exposee. La liste ne retourne que les
 * produits ENCORE publics (vendeur opt-in + produit publie/visible) —
 * fail-closed : un favori dont le produit a ete depublie est filtre.
 */
class RetailMarketFavoriteController extends Controller
{
    public function __construct(private readonly RetailMarketplaceService $marketplace) {}

    /**
     * GET /public/market/account/favorites — DTO produits publics.
     */
    public function index(): JsonResponse
    {
        $buyer = $this->currentBuyer();
        $eligible = $this->marketplace->eligibleCompanyIds();

        $favorites = MarketplaceFavorite::query()
            ->where('buyer_id', (int) $buyer->id)
            ->orderByDesc('id')
            ->get();

        $products = [];

        foreach ($favorites as $favorite) {
            $product = $this->marketplace->findEligibleProduct($eligible, (int) $favorite->product_id);

            if ($product instanceof RetailProduct
                && (string) $product->company_id === (string) $favorite->company_id) {
                $products[] = $product;
            }
        }

        $companyIds = array_values(array_unique(array_map(
            static fn (RetailProduct $product): string => (string) $product->company_id,
            $products,
        )));
        $productIds = array_map(static fn (RetailProduct $product): int => (int) $product->id, $products);

        $settings = $this->marketplace->settingsByCompanyId($companyIds);
        $companies = $this->marketplace->companiesById($companyIds);
        $available = $this->marketplace->availableProductIds($eligible, $productIds);
        $ratings = $this->marketplace->productRatings($productIds);
        $categories = $this->categoriesById($eligible, $products);

        return response()->json([
            'data' => array_map(
                fn (RetailProduct $product): array => $this->productPayload(
                    $product,
                    $settings[(string) $product->company_id] ?? null,
                    $companies[(string) $product->company_id] ?? null,
                    $categories,
                    in_array((int) $product->id, $available, true),
                    $ratings[(int) $product->id] ?? null,
                ),
                $products,
            ),
        ]);
    }

    /**
     * POST /public/market/account/favorites — ajout idempotent d'un
     * produit public (404 fail-closed si produit non eligible).
     */
    public function store(StoreMarketFavoriteRequest $request): JsonResponse
    {
        $buyer = $this->currentBuyer();
        $eligible = $this->marketplace->eligibleCompanyIds();

        $product = $this->marketplace->findEligibleProduct($eligible, (int) $request->input('product_id'));

        if (! $product instanceof RetailProduct) {
            abort(404);
        }

        $favorite = MarketplaceFavorite::query()->firstOrCreate([
            'buyer_id' => (int) $buyer->id,
            'company_id' => (string) $product->company_id,
            'product_id' => (int) $product->id,
        ]);

        return response()->json([
            'data' => ['product_id' => (int) $product->id, 'favorite' => true],
        ], $favorite->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * DELETE /public/market/account/favorites/{productId} — retrait
     * idempotent.
     */
    public function destroy(string $productId): JsonResponse
    {
        $buyer = $this->currentBuyer();

        if (! ctype_digit($productId)) {
            abort(404);
        }

        MarketplaceFavorite::query()
            ->where('buyer_id', (int) $buyer->id)
            ->where('product_id', (int) $productId)
            ->delete();

        return response()->json([
            'data' => ['product_id' => (int) $productId, 'favorite' => false],
        ]);
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

    /**
     * Categories referencees, bornees aux vendeurs opt-in (copie du DTO
     * public de RetailMarketPublicController — meme contrat §3.1).
     *
     * @param  list<string>  $eligible
     * @param  list<RetailProduct>  $products
     * @return array<int, RetailCategory>
     */
    private function categoriesById(array $eligible, array $products): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map(
            static fn (RetailProduct $product): ?int => $product->category_id !== null ? (int) $product->category_id : null,
            $products,
        ), static fn (?int $id): bool => $id !== null)));

        if ($eligible === [] || $categoryIds === []) {
            return [];
        }

        return RetailCategory::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy(fn (RetailCategory $category): int => (int) $category->id)
            ->all();
    }

    /**
     * DTO produit public (contrat §3.1 + agregats rating #7814) — AUCUNE
     * donnee interne.
     *
     * @param  array<int, RetailCategory>  $categories
     * @param  array{rating_avg: float, rating_count: int}|null  $rating
     * @return array<string, mixed>
     */
    private function productPayload(
        RetailProduct $product,
        ?RetailOnlineSettings $settings,
        ?Company $company,
        array $categories,
        bool $available,
        ?array $rating,
    ): array {
        $category = $product->category_id !== null
            ? ($categories[(int) $product->category_id] ?? null)
            : null;

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price_minor' => (int) $product->price_minor,
            'currency' => $product->currency,
            'image_url' => $product->image_url,
            'category' => $category instanceof RetailCategory
                ? ['id' => (int) $category->id, 'name' => $category->name]
                : null,
            'seller' => [
                'name' => $settings?->shop_name,
                'slug' => $company !== null ? (string) $company->slug : null,
                'city' => $settings?->city,
            ],
            'available' => $available,
            'rating_avg' => $rating !== null ? $rating['rating_avg'] : null,
            'rating_count' => $rating !== null ? $rating['rating_count'] : 0,
        ];
    }
}
