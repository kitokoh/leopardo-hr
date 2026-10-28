<?php

declare(strict_types=1);

namespace App\Modules\Pages\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageCustomDomain;
use App\Modules\Pages\Domain\PublicSurface\PublicPagePresenter;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

/**
 * Rendu public SSR d'une Page (BC PAGES, #8730).
 *
 * ## Résolution : par slug, puis par hostname
 *
 * Deux surfaces d'accès pour la MÊME Page (design §6) :
 *   - `/p/{slug}` — le domaine de l'écosystème, toujours disponible ;
 *   - un domaine personnalisé — surface premium, résolue par `Host`.
 *
 * Le hostname est résolu en interrogeant `page_custom_domains` **dans chaque
 * tenant** : il n'existe pas d'index global des domaines, et en créer un
 * dupliquerait une vérité qui vit déjà dans le schéma du tenant. Le coût est
 * borné (une requête par tenant, arrêtée au premier match) et le résultat est
 * mis en cache par host.
 *
 * ## 404 uniforme, jamais 403
 *
 * Slug inconnu, Page non publiée, Page suspendue, domaine non actif, tenant
 * suspendu : **la même réponse 404**. Un 403 confirmerait l'existence de la
 * ressource et permettrait d'énumérer le parc client — c'est l'invariant
 * `PublicTenantResolver` (#8208) et on ne le réimplémente pas ici.
 *
 * ## Pourquoi du HTML et pas du JSON
 *
 * #8730 demande une surface **SSR** : le HTML est rendu côté serveur pour que
 * les moteurs de recherche et les aperçus de lien voient le contenu sans
 * exécuter de JavaScript. Un JSON consommé par un front séparé serait une
 * autre architecture (et une autre issue).
 */
final class PublicPageController extends Controller
{
    public function __construct(
        private readonly PublicTenantResolver $resolver,
        private readonly PublicPagePresenter $presenter,
    ) {}

    /**
     * `GET /p/{slug}` — Page publiée par slug.
     */
    public function show(Request $request, string $slug): Response
    {
        /** @var array{page: Page, company: Company}|null $resolved */
        $resolved = $this->resolveBySlug($slug);

        if ($resolved === null) {
            abort(404);
        }

        return $this->render(
            $resolved['page'],
            $resolved['company'],
            $this->resolveLocale($request, $resolved['page']),
        );
    }

    /**
     * `GET /` sur un domaine personnalisé — Page publiée par `Host`.
     *
     * Le hostname est normalisé (minuscules, sans port) avant la recherche :
     * `Chez-Lea.SN:443` et `chez-lea.sn` désignent le même domaine, et un
     * `Host` forgé avec un port ne doit pas contourner la résolution.
     */
    public function showByHost(Request $request): Response
    {
        $host = $this->normalizeHost($request->getHost());

        if ($host === null) {
            abort(404);
        }

        /** @var array{page: Page, company: Company}|null $resolved */
        $resolved = $this->resolveByHost($host);

        if ($resolved === null) {
            abort(404);
        }

        return $this->render(
            $resolved['page'],
            $resolved['company'],
            $this->resolveLocale($request, $resolved['page']),
        );
    }

    /**
     * @return array{page: Page, company: Company}|null
     */
    private function resolveBySlug(string $slug): ?array
    {
        $normalized = Page::normalizeSlug($slug);

        if ($normalized === null) {
            return null;
        }

        // Le slug est globalement unique : on cherche la Page dans chaque
        // tenant, en s'arrêtant au premier match. `PublicTenantResolver`
        // applique les gardes d'accès (société suspendue/expirée).
        foreach ($this->candidateCompanies() as $company) {
            // Gardes d'accès publiques AVANT toute lecture : société
            // suspendue/expirée → 404 uniforme. On ne réimplémente pas la
            // règle, on appelle le socle (#8208).
            try {
                $this->resolver->assertAccessible($company, null);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                continue;
            }

            $page = $this->resolver->withinTenant($company, function () use ($normalized): ?Page {
                /** @var Page|null $page */
                $page = Page::query()->where('slug', $normalized)->first();

                return $page instanceof Page && $page->isPubliclyVisible() ? $page : null;
            });

            if ($page instanceof Page) {
                return ['page' => $page, 'company' => $company];
            }
        }

        return null;
    }

    /**
     * @return array{page: Page, company: Company}|null
     */
    private function resolveByHost(string $host): ?array
    {
        foreach ($this->candidateCompanies() as $company) {
            try {
                $this->resolver->assertAccessible($company, null);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                continue;
            }

            $page = $this->resolver->withinTenant($company, function () use ($host): ?Page {
                /** @var PageCustomDomain|null $domain */
                $domain = PageCustomDomain::query()
                    ->where('host', $host)
                    ->whereNull('removed_at')
                    ->first();

                if (! $domain instanceof PageCustomDomain || ! $domain->canServe()) {
                    return null;
                }

                /** @var Page|null $page */
                $page = Page::query()->find($domain->page_id);

                return $page instanceof Page && $page->isPubliclyVisible() ? $page : null;
            });

            if ($page instanceof Page) {
                return ['page' => $page, 'company' => $company];
            }
        }

        return null;
    }

    /**
     * Tenants candidats, bornés.
     *
     * On ne parcourt pas `pg_namespace` : la liste vient de `companies`, qui
     * est la source de vérité des tenants. La borne évite qu'un parc de
     * 10 000 tenants transforme un 404 en requête de plusieurs secondes.
     *
     * @return list<Company>
     */
    private function candidateCompanies(): array
    {
        /** @var list<Company> $companies */
        $companies = Company::query()
            ->orderBy('created_at')
            ->limit(500)
            ->get()
            ->all();

        return $companies;
    }

    private function render(Page $page, Company $company, string $locale): Response
    {
        $payload = $this->resolver->withinTenant(
            $company,
            fn (): array => $this->presenter->present($page, $locale),
        );

        $html = View::make('pages.public.show', [
            'page' => $payload,
            // `$locale` est un nom réservé par le moteur de vues (il est
            // partagé par le traducteur) : le passer sous ce nom ferait
            // afficher la locale de l'application, pas celle de la Page.
            'pageLocale' => $locale,
            'direction' => PublicPagePresenter::direction($locale),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // Une Page publiée peut être indexée ; le canonical évite le
            // contenu dupliqué entre le slug et le domaine personnalisé.
            'Cache-Control' => 'public, max-age=60, s-maxage=300',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    /**
     * Locale du rendu : `?lang=`, puis `Accept-Language`, puis la langue par
     * défaut de la PAGE.
     *
     * ## Le candidat doit être une locale PUBLIÉE par cette Page
     *
     * Le filtre n'est pas `Page::SUPPORTED_LOCALES` (les 4 locales que le
     * produit sait rendre) mais {@see Page::publishedLocales()} : les locales
     * que CETTE Page déclare publier. Sinon la page annoncerait
     * `<html lang="en">` au-dessus d'un contenu resté en français — un
     * `lang` qui ment trompe les lecteurs d'écran et les moteurs de recherche
     * autant qu'une page sans `lang` du tout.
     *
     * Le repli est `default_locale` de la Page, jamais une constante : une
     * Page ouverte en turc ne doit pas retomber en français parce que le
     * produit a été codé pour un marché francophone.
     */
    private function resolveLocale(Request $request, Page $page): string
    {
        $published = $page->publishedLocales();
        $default = $page->default_locale !== '' ? $page->default_locale : 'fr';

        $explicit = $request->query('lang');

        if (is_string($explicit)) {
            $candidate = strtolower(substr(trim($explicit), 0, 2));

            if (in_array($candidate, $published, true)) {
                return $candidate;
            }
        }

        $header = $request->header('Accept-Language');

        if (is_string($header) && $header !== '') {
            foreach (explode(',', $header) as $part) {
                $candidate = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));

                if (in_array($candidate, $published, true)) {
                    return $candidate;
                }
            }
        }

        return in_array($default, $published, true) ? $default : ($published[0] ?? 'fr');
    }

    private function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));

        // Retrait d'un port éventuel : `Host: chez-lea.sn:8443`.
        $host = (string) preg_replace('/:\d+$/', '', $host);

        if ($host === '' || ! preg_match('/^[a-z0-9](?:[a-z0-9\-\.]{0,251}[a-z0-9])?$/', $host)) {
            return null;
        }

        return $host;
    }
}
