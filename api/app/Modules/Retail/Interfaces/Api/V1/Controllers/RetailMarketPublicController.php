<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Models\RetailCategory;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vitrine PUBLIQUE de la marketplace Leopardo Marche (BC-17 RETAIL, #7807).
 *
 * Routes isolees (`throttle:shop-public`, SANS auth — spec §3.1) :
 *   GET /public/market/products            → recherche cross-tenant paginee
 *   GET /public/market/products/{id}       → fiche produit publique
 *   GET /public/market/sellers             → boutiques activees
 *   GET /public/market/sellers/{sellerSlug}→ boutique + categories publiques
 *
 * DTO public STRICT (spec §"Public vs prive strictement separes") : jamais
 * de company_id, de quantites de stock, de couts/marges ni de meta — seuls
 * id, name, description, price_minor, currency, image_url, category{id,name},
 * seller{name, slug, city} et `available` (bool) sont exposes. Perimetre
 * borne aux vendeurs OPT-IN via RetailMarketplaceService (fail-closed).
 */
class RetailMarketPublicController extends Controller
{
    public function __construct(private readonly RetailMarketplaceService $marketplace) {}

    /**
     * GET /public/market/products — recherche cross-tenant paginee
     * (q, seller, category, min_price, max_price, sort, per_page ≤ 50).
     */
    public function products(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $filters */
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'seller' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'in:recent,price_asc,price_desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $eligible = $this->marketplace->eligibleCompanyIds();

        $products = $this->marketplace->searchProducts(
            eligible: $eligible,
            q: isset($filters['q']) ? (string) $filters['q'] : null,
            sellerSlug: isset($filters['seller']) ? (string) $filters['seller'] : null,
            categoryId: isset($filters['category']) ? (int) $filters['category'] : null,
            minPriceMinor: isset($filters['min_price']) ? (int) $filters['min_price'] : null,
            maxPriceMinor: isset($filters['max_price']) ? (int) $filters['max_price'] : null,
            sort: isset($filters['sort']) ? (string) $filters['sort'] : 'recent',
            perPage: isset($filters['per_page']) ? (int) $filters['per_page'] : 20,
        );

        /** @var list<RetailProduct> $items */
        $items = $products->items();

        $companyIds = array_values(array_unique(array_map(
            static fn (RetailProduct $product): string => (string) $product->company_id,
            $items,
        )));
        $productIds = array_map(static fn (RetailProduct $product): int => (int) $product->id, $items);

        $settings = $this->marketplace->settingsByCompanyId($companyIds);
        $companies = $this->marketplace->companiesById($companyIds);
        $available = $this->marketplace->availableProductIds($eligible, $productIds);
        $categories = $this->categoriesById($eligible, $items);

        return response()->json([
            'data' => array_map(
                fn (RetailProduct $product): array => $this->productPayload(
                    $product,
                    $settings[(string) $product->company_id] ?? null,
                    $companies[(string) $product->company_id] ?? null,
                    $categories,
                    in_array((int) $product->id, $available, true),
                ),
                $items,
            ),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /**
     * GET /public/market/products/{id} — fiche publique (404 fail-closed si
     * produit inconnu, non publie, non visible en ligne ou vendeur non
     * opt-in).
     */
    public function product(string $id): JsonResponse
    {
        $eligible = $this->marketplace->eligibleCompanyIds();

        $product = ctype_digit($id)
            ? $this->marketplace->findEligibleProduct($eligible, (int) $id)
            : null;

        if (! $product instanceof RetailProduct) {
            abort(404);
        }

        $companyId = (string) $product->company_id;
        $settings = $this->marketplace->settingsByCompanyId([$companyId]);
        $companies = $this->marketplace->companiesById([$companyId]);
        $available = $this->marketplace->availableProductIds($eligible, [(int) $product->id]);
        $categories = $this->categoriesById($eligible, [$product]);

        return response()->json([
            'data' => $this->productPayload(
                $product,
                $settings[$companyId] ?? null,
                $companies[$companyId] ?? null,
                $categories,
                in_array((int) $product->id, $available, true),
            ),
        ]);
    }

    /**
     * GET /public/market/sellers — boutiques activees (nom public, slug,
     * ville, description, nombre de produits publics).
     */
    public function sellers(): JsonResponse
    {
        $eligible = $this->marketplace->eligibleCompanyIds();

        $settings = $this->marketplace->settingsByCompanyId($eligible);
        $companies = $this->marketplace->companiesById($eligible);

        $counts = [];

        if ($eligible !== []) {
            /** @var array<string, int> $counts */
            $counts = RetailProduct::query()
                ->withoutGlobalScope('company')
                ->whereIn('company_id', $eligible)
                ->where('status', RetailProductStatus::Published->value)
                ->where('online_visible', true)
                ->selectRaw('company_id, COUNT(*) AS products_count')
                ->groupBy('company_id')
                ->pluck('products_count', 'company_id')
                ->all();
        }

        $sellers = [];

        // Tri alphabetique stable sur le nom public de la boutique.
        $ordered = collect($settings)
            ->sortBy(fn (RetailOnlineSettings $s): string => mb_strtolower($s->shop_name))
            ->values();

        foreach ($ordered as $sellerSettings) {
            $companyId = (string) $sellerSettings->company_id;
            $company = $companies[$companyId] ?? null;

            if (! in_array($companyId, $eligible, true) || ! $company instanceof Company) {
                continue;
            }

            $sellers[] = [
                'name' => $sellerSettings->shop_name,
                'slug' => (string) $company->slug,
                'city' => $sellerSettings->city,
                'description' => $sellerSettings->shop_description,
                'products_count' => $counts[$companyId] ?? 0,
            ];
        }

        return response()->json(['data' => $sellers]);
    }

    /**
     * GET /public/market/sellers/{sellerSlug} — boutique + ses categories
     * publiques. Tenant resolu par `market.public` (404 fail-closed) :
     * les requetes scoped ici sont bornees au vendeur courant.
     */
    public function seller(): JsonResponse
    {
        $company = currentCompany();

        /** @var RetailOnlineSettings $settings */
        $settings = RetailOnlineSettings::query()->firstOrFail();

        $products = RetailProduct::query()
            ->where('status', RetailProductStatus::Published->value)
            ->where('online_visible', true)
            ->get(['id', 'category_id']);

        $countsByCategory = [];

        foreach ($products as $product) {
            if ($product->category_id === null) {
                continue;
            }

            $key = (int) $product->category_id;
            $countsByCategory[$key] = ($countsByCategory[$key] ?? 0) + 1;
        }

        $categories = $countsByCategory === [] ? [] : RetailCategory::query()
            ->whereIn('id', array_keys($countsByCategory))
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (RetailCategory $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'products_count' => $countsByCategory[(int) $category->id] ?? 0,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'name' => $settings->shop_name,
                'slug' => (string) $company->slug,
                'city' => $settings->city,
                'description' => $settings->shop_description,
                'contact_phone' => $settings->contact_phone,
                'contact_email' => $settings->contact_email,
                'currency' => $settings->currency,
                'products_count' => $products->count(),
                'categories' => $categories,
            ],
        ]);
    }

    /**
     * Categories referencees par les produits affiches, bornees aux
     * vendeurs opt-in, indexees par id.
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
     * DTO produit public — AUCUNE donnee interne (spec §3.1).
     *
     * @param  array<int, RetailCategory>  $categories
     * @return array<string, mixed>
     */
    private function productPayload(
        RetailProduct $product,
        ?RetailOnlineSettings $settings,
        ?Company $company,
        array $categories,
        bool $available,
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
        ];
    }
}
