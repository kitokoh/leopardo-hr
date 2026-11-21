<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * BC-10 (#9220) — annuaire public des portails carrières.
 *
 * Rend la liste des entreprises qui ont AU MOINS une offre publiée, pour que le
 * sitemap puisse déclarer leurs pages carrières. Sans cet endpoint, le sitemap
 * devrait soit deviner les slugs (impossible), soit ne rien déclarer — et les
 * pages carrières resteraient invisibles pour Google Jobs.
 *
 * Ce que cet endpoint ne fait PAS, délibérément :
 *
 *  - il ne liste pas TOUS les tenants, seulement ceux qui ont une offre
 *    publiée : déclarer au sitemap une page carrières vide enverrait les
 *    robots vers une page sans contenu, ce que Google pénalise ;
 *  - il n'expose aucune donnée d'entreprise au-delà du strict nécessaire
 *    (slug, nom, date de dernière publication) : c'est un index de crawl, pas
 *    un annuaire commercial.
 *
 * Le `lastmod` est la date de la dernière offre publiée, pas `now()` : un
 * `lastmod` qui change à chaque requête fait churner le crawl sans qu'aucun
 * contenu n'ait bougé.
 *
 * Les offres vivent dans le schéma partagé `shared_tenants` (ADR-0028 : le mode
 * schema-per-tenant est mort) et sont filtrées par `company_id`. On passe donc
 * par `TenantManager::withinTenant` — le même chemin que le reste du module —
 * plutôt que d'écrire une requête non qualifiée qui dépendrait du `search_path`
 * courant.
 */
class PublicCareersDirectoryController extends Controller
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    /**
     * GET /api/v1/public/careers
     *
     * ## #9697 — recherche et pagination
     *
     * L'endpoint ne servait qu'au sitemap : il rendait TOUTES les entreprises
     * d'un coup, sans filtre. Depuis que `/emploi` est un annuaire grand public
     * (et non plus un simple index de crawl), il doit se comporter comme les
     * autres annuaires de la plateforme : `q` filtre sur le nom, `page`/
     * `per_page` bornent la réponse.
     *
     * Rétrocompatibilité : `meta.count` reste le nombre TOTAL d'entreprises
     * éligibles (pas la taille de la page courante) — le sitemap s'en sert pour
     * décider s'il déclare quelque chose, et une valeur qui changerait de sens
     * selon la page demandée serait un piège.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $companies = Company::query()
            ->whereNotIn('status', ['suspended', 'expired'])
            ->orderBy('slug')
            ->get(['id', 'name', 'slug', 'country']);

        $entries = [];

        foreach ($companies as $company) {
            $latest = $this->latestPublishedAt($company);

            if ($latest === null) {
                // Aucune offre publiée : la page carrières n'a rien à indexer.
                continue;
            }

            $entries[] = [
                'slug' => $company->slug,
                'name' => $company->name,
                'country' => $company->country,
                'last_published_at' => $latest,
            ];
        }

        // Filtre `q` appliqué APRÈS le filtre de publication : on ne cherche que
        // parmi les entreprises réellement visibles, et le total annoncé reste
        // celui des résultats de la recherche.
        $needle = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        if ($needle !== '') {
            $entries = array_values(array_filter(
                $entries,
                static fn (array $entry): bool => str_contains(
                    mb_strtolower((string) $entry['name']),
                    $needle,
                ) || str_contains(mb_strtolower((string) $entry['slug']), $needle),
            ));
        }

        $total = count($entries);
        $perPage = (int) ($filters['per_page'] ?? 24);
        $page = (int) ($filters['page'] ?? 1);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $entries = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return new JsonResponse([
            'data' => $entries,
            'meta' => [
                'count' => $total,
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Date de la dernière offre publiée d'un tenant, ou `null` s'il n'en a
     * aucune.
     */
    private function latestPublishedAt(Company $company): ?string
    {
        try {
            return $this->tenantManager->withinTenant($company, function () use ($company): ?string {
                $latest = JobPosting::query()
                    ->where('company_id', $company->id)
                    ->published()
                    ->where(function ($q): void {
                        $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
                    })
                    ->max('published_at');

                return is_string($latest) ? $latest : null;
            });
        } catch (Throwable) {
            // Un tenant dont le contexte ne peut pas être résolu (migration en
            // cours, schéma incohérent) ne doit pas casser l'annuaire entier :
            // on l'ignore, les autres restent déclarés.
            return null;
        }
    }
}
