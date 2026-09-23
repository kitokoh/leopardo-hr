<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcaseLocales;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\VitrinePublicResource;
use App\Shared\Contracts\Catalog\PublishedProductsProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * BC-27 SHOWCASE — API publique d'une vitrine (V-PUBLIC-API #6867, étendue
 * V-PUBLISH #6871, V-I18N #6874, V-SEO #6873, V-RGPD #6875, C-VITRINE #6891).
 *
 * Route isolée SANS auth (groupe `throttle:shop-public`), zéro donnée
 * interne : le tenant est résolu par le slug (`companies.slug` en schéma
 * public), puis lecture dans le schéma tenant via
 * TenantManager::withinTenant avec le scope company_id. Le DTO public
 * (VitrinePublicResource) est le SEUL contrat exposé.
 *
 * - `GET /public/vitrine/{slug}` : vitrine publiée, localisée
 *   (`?lang=fr|en|ar|tr`, sinon Accept-Language, sinon fr) ;
 * - `?token=` (jeton d'aperçu #6871) : sert aussi un brouillon, avec
 *   `X-Robots-Tag: noindex` — jamais mis en cache ni indexable ;
 * - 404 si slug inconnu / société suspendue / vitrine absente, non publiée
 *   sans jeton valide ;
 * - `GET /public/vitrine/sitemap.xml` : vitrines publiées uniquement ;
 * - `GET /public/vitrine/robots.txt` : indexation + pointeur sitemap.
 */
final class ShowcasePublicController extends Controller
{
    private const SITEMAP_CACHE_KEY = 'showcase:public:sitemap';

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ShowcasePublicCache $cache,
        private readonly PublishedProductsProvider $productsProvider,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = ShowcaseLocales::resolve($request);
        $providedToken = $request->query('token');
        $providedToken = is_string($providedToken) && $providedToken !== '' ? $providedToken : null;

        /** @var array<string, mixed>|null $payload */
        $payload = null;
        $isPreview = false;

        $resolver = function () use ($request, $slug, $locale, $providedToken, &$isPreview): ?array {
            /** @var Company|null $company */
            $company = Company::query()
                ->where('slug', $slug)
                ->where('status', '!=', 'suspended')
                ->first();

            if (! $company instanceof Company) {
                return null;
            }

            return $this->tenantManager->withinTenant($company, function () use ($company, $request, $slug, $locale, $providedToken, &$isPreview): ?array {
                /** @var CompanyShowcase|null $showcase */
                $showcase = CompanyShowcase::query()
                    ->where('slug', $slug)
                    ->first();

                if (! $showcase instanceof CompanyShowcase) {
                    return null;
                }

                $isPublished = $showcase->status === CompanyShowcaseStatus::Published;
                $previewAuthorized = ! $isPublished
                    && $providedToken !== null
                    && $showcase->preview_token !== null
                    && hash_equals($showcase->preview_token, $providedToken);

                if (! $isPublished && ! $previewAuthorized) {
                    return null;
                }

                // Un jeton fourni sur une vitrine publiée reste un aperçu :
                // pas d'indexation (évite le contenu dupliqué).
                if ($providedToken !== null) {
                    $isPreview = true;
                }

                /** @var list<CompanyShowcaseSection> $sections */
                $sections = CompanyShowcaseSection::query()
                    ->where('showcase_id', $showcase->id)
                    ->ordered()
                    ->get()
                    ->all();

                $resource = new VitrinePublicResource($showcase, $sections, $company->name, $locale, $this->productsProvider);

                return $resource->resolve($request);
            });
        };

        if ($providedToken !== null) {
            // Aperçu : jamais servi depuis le cache.
            $payload = $resolver();
        } else {
            $payload = $this->cache->remember($slug, $locale, $resolver);
        }

        if ($payload === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $response = response()->json(['data' => $payload]);

        if ($isPreview) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * V-SEO #6873 — sitemap des vitrines PUBLIÉES (jamais de brouillon).
     * Liste cross-tenant bornée et mise en cache court (15 min).
     */
    public function sitemap(): HttpResponse
    {
        /** @var string $xml */
        // tenant-cache:shared — sitemap vitrines GLOBAL (agrégat cross-tenant voulu) (#8058)
        $xml = Cache::remember(self::SITEMAP_CACHE_KEY, now()->addSeconds(ShowcasePublicCache::TTL_SECONDS), function (): string {
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
                $this->tenantManager->withinTenant($company, function () use ($company, &$lines): void {
                    /** @var CompanyShowcase|null $showcase */
                    $showcase = CompanyShowcase::query()
                        ->where('company_id', $company->id)
                        ->where('status', CompanyShowcaseStatus::Published)
                        ->first();

                    if (! $showcase instanceof CompanyShowcase) {
                        return;
                    }

                    $lastmod = ($showcase->published_at ?? $showcase->updated_at)?->toAtomString();

                    $lines[] = '  <url>';
                    $lines[] = '    <loc>'.htmlspecialchars('/public/vitrine/'.$showcase->slug, ENT_XML1 | ENT_QUOTES).'</loc>';
                    if ($lastmod !== null) {
                        $lines[] = '    <lastmod>'.$lastmod.'</lastmod>';
                    }
                    foreach (ShowcaseLocales::supported() as $locale) {
                        $lines[] = '    <xhtml:link rel="alternate" hreflang="'.$locale.'" href="'.htmlspecialchars('/public/vitrine/'.$showcase->slug.'?lang='.$locale, ENT_XML1 | ENT_QUOTES).'"/>';
                    }
                    $lines[] = '  </url>';
                });
            }

            $lines[] = '</urlset>';

            return implode("\n", $lines);
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * V-SEO #6873 — robots.txt public : indexation autorisée sur les
     * vitrines, aperçus exclus, pointeur sitemap.
     */
    public function robots(): HttpResponse
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /public/vitrine/',
            'Disallow: /public/vitrine/*?token=',
            'Sitemap: /public/vitrine/sitemap.xml',
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
