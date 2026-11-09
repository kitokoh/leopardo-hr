<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Http\JsonResponse;
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
     */
    public function index(): JsonResponse
    {
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

        return new JsonResponse([
            'data' => $entries,
            'meta' => ['count' => count($entries)],
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
