<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Catalogue B2B PUBLIC d'un tenant (BC-28 CATALOG, C-PUBLIC #6882).
 *
 * Routes isolées (`throttle:shop-public` + `catalog.public`, SANS auth) :
 *   GET /public/catalog/{companySlug}                        → liste publique
 *   GET /public/catalog/{companySlug}/products/{productSlug} → fiche publique
 *
 * Le tenant est résolu par slug dans EnsureCatalogPublicAccess (404
 * fail-closed si slug inconnu / company suspendue-expirée / flag absent).
 *
 * DTO public STRICT — jamais de modèles internes : seuls nom, slug,
 * description, prix indicatif (minor units) + devise ISO + unité, et
 * catégorie {slug, name} sont exposés. Ni company_id, ni id, ni status,
 * ni `meta` (attributs/specs internes — revue RGPD spec §"public vs privé"),
 * ni stocks/marges/fournisseurs. Les médias arrivent avec C-MEDIA (#6885).
 *
 * Cache Redis TTL (`catalog.public_cache_ttl`, 600 s) : snapshot liste +
 * fiches produits, invalidés à la publication/dépublication et sur chaque
 * mutation tenant (CatalogPublicCache). 404 propre pour draft/inconnu.
 */
class CatalogPublicController extends Controller
{
    /**
     * GET /public/catalog/{companySlug} — catégories + produits publiés.
     * Filtre optionnel `?category={categorySlug}` appliqué après cache.
     */
    public function index(Request $request): JsonResponse
    {
        $company = currentCompany();

        /** @var array<string, mixed> $snapshot */
        // tenant-cache:via-helper — clé construite par TenantCache::keyFor (#8058)
        $snapshot = Cache::remember(
            CatalogPublicCache::snapshotKey($company->id),
            (int) config('catalog.public_cache_ttl', 600),
            fn (): array => $this->buildSnapshot($company)
        );

        $categorySlug = trim((string) $request->query('category', ''));

        if ($categorySlug !== '') {
            $snapshot['products'] = array_values(array_filter(
                $snapshot['products'],
                fn (array $product): bool => ($product['category']['slug'] ?? null) === $categorySlug
            ));
        }

        return response()->json(['data' => $snapshot]);
    }

    /**
     * GET /public/catalog/sitemap.xml — SEO (#6888) : produits PUBLIES de
     * toutes les sociétés actives (jamais de brouillon), borné et caché.
     * Route hors groupe tenant (`catalog.public` résout un slug) — donc
     * déclarée séparément dans catalog_public.php.
     */
    public function sitemap(): \Illuminate\Http\Response
    {
        /** @var string $xml */
        // tenant-cache:shared — sitemap public GLOBAL (toutes les boutiques publiées), agrégat cross-tenant voulu (#8058)
        $xml = Cache::remember('catalog:public:sitemap', now()->addSeconds((int) config('catalog.public_cache_ttl', 600)), function (): string {
            $lines = [
                '<?xml version="1.0" encoding="UTF-8"?>',
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            ];

            /** @var list<Company> $companies */
            $companies = Company::query()
                ->where('status', '!=', 'suspended')
                ->limit(500)
                ->get()
                ->all();

            foreach ($companies as $company) {
                app(TenantManager::class)->withinTenant($company, function () use ($company, &$lines): void {
                    /** @var list<CatalogProduct> $products */
                    $products = CatalogProduct::query()
                        ->where('status', CatalogProductStatus::Published->value)
                        ->orderBy('name')
                        ->limit(1000)
                        ->get()
                        ->all();

                    foreach ($products as $product) {
                        $lastmod = $product->updated_at?->toAtomString();
                        $loc = '/public/catalog/'.$company->slug.'/products/'.$product->slug;

                        $lines[] = '  <url>';
                        $lines[] = '    <loc>'.htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES).'</loc>';
                        if ($lastmod !== null) {
                            $lines[] = '    <lastmod>'.$lastmod.'</lastmod>';
                        }
                        $lines[] = '  </url>';
                    }
                });
            }

            $lines[] = '</urlset>';

            return implode("\n", $lines);
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * GET /public/catalog/{companySlug}/products/{productSlug} — fiche
     * publique d'un produit publié (404 si draft, inconnu ou cross-tenant).
     */
    public function show(string $companySlug, string $productSlug): JsonResponse
    {
        $company = currentCompany();

        /** @var array<string, mixed>|null $product */
        // tenant-cache:via-helper — clé construite par TenantCache::keyFor (#8058)
        $product = Cache::remember(
            CatalogPublicCache::productKey($company->id, $productSlug),
            (int) config('catalog.public_cache_ttl', 600),
            fn (): ?array => $this->buildProduct($productSlug)
        );

        if ($product === null) {
            abort(404);
        }

        return response()->json(['data' => $product]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(Company $company): array
    {
        $categories = CatalogCategory::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $products = CatalogProduct::query()
            ->where('status', CatalogProductStatus::Published->value)
            ->orderBy('name')
            ->get();

        return [
            'company' => [
                'slug' => $company->slug,
                'name' => $company->name,
            ],
            'meta' => [
                'title' => $company->name,
                'description' => $company->name,
                'canonical_path' => '/public/catalog/'.$company->slug,
                'indexable' => true,
            ],
            'categories' => $categories
                ->map(fn (CatalogCategory $category): array => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'position' => $category->position,
                ])
                ->values()
                ->all(),
            'products' => $products
                ->map(fn (CatalogProduct $product): array => $this->productPayload($product, $categories))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildProduct(string $productSlug): ?array
    {
        $product = CatalogProduct::query()
            ->where('slug', $productSlug)
            ->where('status', CatalogProductStatus::Published->value)
            ->first();

        if (! $product instanceof CatalogProduct) {
            return null;
        }

        $category = $product->category_id !== null
            ? CatalogCategory::query()->find($product->category_id)
            : null;

        $payload = $this->productPayload($product, $category === null ? collect() : collect([$category]));

        // Fiche produit publique (#6883) : produits publies de la meme
        // categorie (max 3, sans le produit courant) — jamais de brouillon.
        $related = collect();
        if ($category instanceof CatalogCategory) {
            $related = CatalogProduct::query()
                ->where('status', CatalogProductStatus::Published->value)
                ->where('category_id', $category->id)
                ->where('id', '!=', $product->id)
                ->orderBy('name')
                ->limit(3)
                ->get();
        }

        $payload['related'] = $related
            ->map(fn (CatalogProduct $item): array => [
                'slug' => $item->slug,
                'name' => $item->name,
                'price_minor' => $item->price_minor,
                'currency' => $item->currency,
                'unit' => $item->unit,
            ])
            ->values()
            ->all();

        return $payload;
    }

    /**
     * @param  iterable<CatalogCategory>  $categories
     * @return array<string, mixed>
     */
    private function productPayload(CatalogProduct $product, iterable $categories): array
    {
        $category = $product->category_id !== null
            ? collect($categories)->first(fn (CatalogCategory $c): bool => $c->id === $product->category_id)
            : null;

        $meta = is_array($product->meta) ? $product->meta : [];

        /** @var list<string> $photos */
        $photos = [];
        foreach ((array) ($meta['photos'] ?? []) as $photo) {
            if (is_string($photo) && $photo !== '') {
                $photos[] = $photo;
            }
        }

        $ogImage = $photos[0] ?? null;

        return [
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'price_minor' => $product->price_minor,
            'currency' => $product->currency,
            'unit' => $product->unit,
            'photos' => $photos,
            'category' => $category instanceof CatalogCategory
                ? ['slug' => $category->slug, 'name' => $category->name]
                : null,
            // Fiche produit publique (#6883) : CTA devis (formulaire public BC-28).
            'inquiry_path' => '/public/catalog/'.currentCompany()->slug.'/inquiries',
            // SEO fiche (#6888) : meta derivees du contenu, aucun champ interne.
            'meta' => [
                'title' => $product->name,
                'description' => $product->description,
                'og_image' => $ogImage,
                'canonical_path' => $this->canonicalPath($product),
                'indexable' => true,
            ],
        ];
    }

    /**
     * Chemin canonique d'une fiche produit (tenant courant — middleware
     * `catalog.public` a resolu la societe par slug).
     */
    private function canonicalPath(CatalogProduct $product): string
    {
        return '/public/catalog/'.currentCompany()->slug.'/products/'.$product->slug;
    }
}
