<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Retail\Domain\Models\RetailCategory;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use App\Modules\Retail\Infrastructure\Services\RetailMarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #7807 — API publique marketplace Retail : decouverte cross-tenant
 * des produits et boutiques opt-in (`/public/market/*`, sans auth,
 * `throttle:shop-public` — pattern TravelMarketplaceController #7737).
 *
 * DTO public STRICT (fail-closed) : id, name, description, price_minor,
 * currency, image_url, category, seller, available (bool). JAMAIS de donnee
 * interne : pas de stock chiffre, pas de marges (cost_minor), pas de
 * company_id — la boutique est identifiee par son slug public.
 */
class RetailMarketController extends Controller
{
    public function __construct(private readonly RetailMarketplaceService $marketplace) {}

    /**
     * Recherche publique cross-tenant des produits en ligne.
     */
    public function products(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'seller' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:160'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'string', 'in:price_asc,price_desc,newest,name'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $products = $this->marketplace->searchProducts(
            q: isset($filters['q']) ? (string) $filters['q'] : null,
            sellerSlug: isset($filters['seller']) ? (string) $filters['seller'] : null,
            category: isset($filters['category']) ? (string) $filters['category'] : null,
            minPriceMinor: isset($filters['min_price']) ? (int) $filters['min_price'] : null,
            maxPriceMinor: isset($filters['max_price']) ? (int) $filters['max_price'] : null,
            sort: isset($filters['sort']) ? (string) $filters['sort'] : null,
            perPage: isset($filters['per_page']) ? (int) $filters['per_page'] : 20,
        );

        /** @var list<RetailProduct> $items */
        $items = $products->items();

        $sellers = $this->sellersByCompanyId($items);

        return response()->json([
            'data' => collect($items)
                ->map(fn (RetailProduct $product): array => $this->productPayload(
                    $product,
                    $sellers[(string) $product->company_id] ?? null,
                ))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * Fiche publique d'un produit (tenant resolu PAR PRODUIT, 404
     * fail-closed : produit inconnu, non publie, non visible en ligne ou
     * boutique non opt-in — reponse indistincte).
     */
    public function product(string $product): JsonResponse
    {
        $model = ctype_digit($product) ? $this->marketplace->findEligibleProduct((int) $product) : null;

        if (! $model instanceof RetailProduct) {
            abort(404);
        }

        $sellers = $this->sellersByCompanyId([$model]);

        return response()->json([
            'data' => $this->productPayload($model, $sellers[(string) $model->company_id] ?? null),
        ]);
    }

    /**
     * Annuaire public des boutiques opt-in.
     */
    public function sellers(): JsonResponse
    {
        return response()->json([
            'data' => collect($this->marketplace->sellers())
                ->map(fn (RetailOnlineSettings $settings): array => $this->sellerPayload($settings))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Fiche publique d'une boutique par slug (404 fail-closed).
     */
    public function seller(string $slug): JsonResponse
    {
        $settings = $this->marketplace->findEligibleSellerBySlug($slug);

        if (! $settings instanceof RetailOnlineSettings) {
            abort(404);
        }

        return response()->json(['data' => $this->sellerPayload($settings)]);
    }

    /**
     * Boutiques (slug + nom public) indexees par company_id pour un lot de
     * produits — usage interne au controleur, le company_id n'est JAMAIS
     * expose dans la reponse.
     *
     * @param  list<RetailProduct>  $products
     * @return array<string, RetailOnlineSettings>
     */
    private function sellersByCompanyId(array $products): array
    {
        $companyIds = collect($products)
            ->map(fn (RetailProduct $product): string => (string) $product->company_id)
            ->unique()
            ->values();

        if ($companyIds->isEmpty()) {
            return [];
        }

        return RetailOnlineSettings::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $companyIds)
            ->get()
            ->keyBy(fn (RetailOnlineSettings $settings): string => (string) $settings->company_id)
            ->all();
    }

    /**
     * DTO public d'un produit — spec §3.1. `available` est un BOOLEEN
     * derive : stock non suivi (aucun niveau) => disponible ; sinon somme
     * des quantites > 0. Le chiffre exact n'est jamais expose.
     *
     * @return array<string, mixed>
     */
    private function productPayload(RetailProduct $product, ?RetailOnlineSettings $seller): array
    {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price_minor' => (int) $product->price_minor,
            'currency' => $product->currency,
            'image_url' => $product->image_url,
            'category' => $this->categoryName($product),
            'seller' => $seller instanceof RetailOnlineSettings ? [
                'slug' => $seller->slug,
                'display_name' => $seller->display_name,
            ] : null,
            'available' => $this->isAvailable($product),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerPayload(RetailOnlineSettings $settings): array
    {
        return [
            'slug' => $settings->slug,
            'display_name' => $settings->display_name,
            'description' => $settings->description,
            'product_count' => $this->marketplace->publicProductCount($settings),
        ];
    }

    private function categoryName(RetailProduct $product): ?string
    {
        if ($product->category_id === null) {
            return null;
        }

        /** @var RetailCategory|null $category */
        $category = RetailCategory::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $product->company_id)
            ->find($product->category_id);

        return $category?->name;
    }

    private function isAvailable(RetailProduct $product): bool
    {
        $levels = RetailStockLevel::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $product->company_id)
            ->where('product_id', (int) $product->id)
            ->get();

        if ($levels->isEmpty()) {
            // Stock non suivi : le produit reste commandable (politique de
            // survente POS #7674 — on n'expose jamais le chiffre).
            return true;
        }

        return $levels->sum(fn (RetailStockLevel $level): float => (float) $level->quantity) > 0;
    }
}
