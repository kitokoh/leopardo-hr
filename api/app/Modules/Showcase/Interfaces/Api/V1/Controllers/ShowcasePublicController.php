<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcaseDoubleReadPolicy;
use App\Modules\Showcase\Domain\Support\ShowcaseLocales;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\VitrinePublicResource;
use App\Shared\Contracts\Catalog\PublishedProductsProvider;
use App\Shared\Contracts\Pages\PageProjectionReader;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
 *
 * BOS-050 (#8208, tranche 3) : la résolution du tenant de `show()` est
 * déléguée au plumbing mutualisé {@see PublicTenantResolver} — invariant
 * partagé des surfaces publiques : fail-closed uniforme 404 (slug
 * inconnu/vide, société `suspended`/`expired` — la surface historique ne
 * filtrait que `suspended` : durcissement voulu, aligné sur le socle),
 * feature `null` (vitrine historique sans flag vertical — cas prévu par le
 * socle), contexte tenant via `withinTenant()` (marqueur
 * `tenant_scope_required` + scope BelongsToCompany, état restauré en
 * `finally`). Cache public par locale, flux d'aperçu `?token=` (jamais
 * caché, `X-Robots-Tag: noindex`), sitemap/robots et DTO public inchangés.
 */
final class ShowcasePublicController extends Controller
{
    private const SITEMAP_CACHE_KEY = 'showcase:public:sitemap';

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly PublicTenantResolver $publicTenantResolver,
        private readonly ShowcasePublicCache $cache,
        private readonly PublishedProductsProvider $productsProvider,
        private readonly PageProjectionReader $pageProjectionReader,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = ShowcaseLocales::resolve($request);
        $providedToken = $request->query('token');
        $providedToken = is_string($providedToken) && $providedToken !== '' ? $providedToken : null;

        // BOS-050 (#8208, tranche 3) — résolution fail-closed AVANT toute
        // lecture de cache : une société suspendue/expirée ne doit jamais
        // être servie, même depuis une entrée cachée antérieure. Le socle
        // lève 404 (anti-énumération) au lieu du `null` historique — la
        // réponse est identique, et les 404 ne sont plus négativement cachés.
        $company = $this->publicTenantResolver->companyBySlug($slug, null);

        /** @var array<string, mixed>|null $payload */
        $payload = null;
        $isPreview = false;

        $resolver = function () use ($request, $company, $slug, $locale, $providedToken, &$isPreview): ?array {
            return $this->publicTenantResolver->withinTenant($company, function () use ($company, $request, $slug, $locale, $providedToken, &$isPreview): ?array {
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

                // #8727 — double lecture : OBSERVATION uniquement. Le payload
                // servi reste celui du legacy (source de vérité) ; on relit la
                // Page projetée pour mesurer l'écart avant la bascule. Aucune
                // exception n'est propagée : une divergence ne doit jamais
                // dégrader une surface publique.
                $this->observeDoubleRead($showcase, \count($sections));

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
     * #8727 — double lecture best-effort : ne lève jamais, ne modifie jamais
     * la réponse. Les erreurs de lecture Pages sont absorbées : une vitrine
     * doit rester servie même si la projection est illisible.
     */
    private function observeDoubleRead(CompanyShowcase $showcase, int $legacySections): void
    {
        if (! (bool) config('pages.double_read.enabled', false)) {
            return;
        }

        $rollout = (int) config('pages.double_read.rollout_percent', 0);

        if (! ShowcaseDoubleReadPolicy::shouldSample((string) $showcase->slug, $rollout)) {
            return;
        }

        try {
            $siteId = $this->resolveCandidateSiteId($showcase);

            if ($siteId === null) {
                return;
            }

            $projection = $this->pageProjectionReader->forSite($siteId);

            if ($projection === null) {
                // Vitrine pas encore backfillée : cas normal, pas une divergence.
                return;
            }

            $divergences = ShowcaseDoubleReadPolicy::compare(
                [
                    'status' => $showcase->status->value,
                    'slug' => (string) $showcase->slug,
                    'sections' => $legacySections,
                ],
                $projection,
            );

            if ($divergences !== [] && (bool) config('pages.double_read.log_divergences', true)) {
                Log::warning(ShowcaseDoubleReadPolicy::summary((string) $showcase->slug, $divergences), [
                    'showcase_id' => $showcase->id,
                ]);
            }
        } catch (Throwable) {
            // Volontairement silencieux : l'observation ne doit jamais devenir
            // un point de panne de la surface publique.
        }
    }

    /**
     * #8727 — résout l'établissement candidat d'une vitrine, ou `null` si la
     * correspondance n'est pas univoque.
     *
     * `company_showcases` ne porte AUCUNE colonne `site_id` : le rattachement
     * vitrine → établissement se déduit de `sites.company_id` (même tenant),
     * exactement comme `showcase:pages-inventory`. La spec
     * (`docs/design/etablissements-pages.md`, `ShowcasePageProjection`) interdit
     * de choisir arbitrairement un Site : `pages.site_id` est UNIQUE (une Page
     * par établissement en V1), donc un tenant à 0 ou plusieurs établissements
     * est une décision produit, pas une heuristique. On ne devine pas : on
     * saute la mesure et l'inventaire remonte le cas comme bloquant.
     */
    private function resolveCandidateSiteId(CompanyShowcase $showcase): ?int
    {
        $siteIds = Site::query()
            ->where('company_id', $showcase->company_id)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id');

        if ($siteIds->count() !== 1) {
            return null;
        }

        return (int) $siteIds->first();
    }

    /**
     * V-SEO #6873 — sitemap des vitrines PUBLIÉES (jamais de brouillon).
     * Liste cross-tenant mise en cache court (15 min).
     *
     * #8731 — trois défauts corrigés sur cette surface :
     *  (1) `xmlns:xhtml` n'était pas déclaré alors que chaque `<url>` émet un
     *      `<xhtml:link rel="alternate">`. Un préfixe non déclaré rend le
     *      document non conforme : les parseurs stricts rejettent le sitemap
     *      entier. Le namespace est désormais déclaré sur `<urlset>`.
     *  (2) `<loc>` était relatif. Le protocole sitemap exige des URL absolues.
     *      Les entrées sont construites depuis `app.url` et jamais depuis
     *      l'en-tête `Host`, qui est contrôlé par le client et permettrait
     *      d'empoisonner le sitemap mis en cache avec un domaine tiers.
     *  (3) `limit(500)` tronquait l'index silencieusement : au-delà de 500
     *      sociétés les vitrines suivantes disparaissaient sans aucun signal.
     *      La lecture est paginée par clé (`chunk`), sans borne arbitraire.
     *
     * Les sociétés `expired` sont exclues comme `suspended` : une société
     * expirée n'est plus servie (`PublicTenantResolver` renvoie 404), donc
     * l'inscrire ne produirait que des URL mortes.
     */
    public function sitemap(): HttpResponse
    {
        /** @var string $xml */
        // tenant-cache:shared — sitemap vitrines GLOBAL (agrégat cross-tenant voulu) (#8058)
        $xml = Cache::remember(self::SITEMAP_CACHE_KEY, now()->addSeconds(ShowcasePublicCache::TTL_SECONDS), function (): string {
            $base = rtrim((string) config('app.url'), '/');

            $lines = [
                '<?xml version="1.0" encoding="UTF-8"?>',
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
                    .' xmlns:xhtml="http://www.w3.org/1999/xhtml">',
            ];

            // #8731 — pagination par clé au lieu de `limit(500)` : aucune
            // vitrine n'est écartée de l'index sans que personne ne le sache.
            Company::query()
                ->whereNotIn('status', ['suspended', 'expired'])
                ->orderBy('id')
                ->chunk(200, function ($companies) use ($base, &$lines): void {
                    /** @var Company $company */
                    foreach ($companies as $company) {
                        $this->tenantManager->withinTenant($company, function () use ($company, $base, &$lines): void {
                            /** @var CompanyShowcase|null $showcase */
                            $showcase = CompanyShowcase::query()
                                ->where('company_id', $company->id)
                                ->where('status', CompanyShowcaseStatus::Published)
                                ->first();

                            if (! $showcase instanceof CompanyShowcase) {
                                return;
                            }

                            $lastmod = ($showcase->published_at ?? $showcase->updated_at)?->toAtomString();
                            $loc = $base.'/public/vitrine/'.$showcase->slug;

                            $lines[] = '  <url>';
                            $lines[] = '    <loc>'.htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES).'</loc>';
                            if ($lastmod !== null) {
                                $lines[] = '    <lastmod>'.$lastmod.'</lastmod>';
                            }
                            foreach (ShowcaseLocales::supported() as $locale) {
                                $alternate = $base.'/public/vitrine/'.$showcase->slug.'?lang='.$locale;
                                $lines[] = '    <xhtml:link rel="alternate" hreflang="'.$locale.'" href="'
                                    .htmlspecialchars($alternate, ENT_XML1 | ENT_QUOTES).'"/>';
                            }
                            $lines[] = '  </url>';
                        });
                    }
                });

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