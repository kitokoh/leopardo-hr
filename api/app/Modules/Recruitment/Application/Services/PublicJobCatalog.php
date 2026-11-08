<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Recruitment\Domain\Models\ExternalJobOffer;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * BC-10 RECRUITMENT — lecture du catalogue public d'offres.
 *
 * Le portail public agrège DEUX sources qui ne vivent pas dans le même schéma :
 *
 * - `job_postings` (schéma tenant) — les offres publiées par l'entreprise
 *   cliente, résolues via `TenantManager::withinTenant` ;
 * - `public.external_job_offers` (schéma public) — les offres importées par la
 *   plateforme depuis les agrégateurs, qui n'appartiennent à aucun tenant.
 *
 * La fusion est faite ICI, explicitement, et jamais par une jointure
 * cross-schéma implicite : c'est ce qui rend l'isolation vérifiable et
 * testable. Les offres agrégées sont rattachées à la page d'un tenant par le
 * PAYS (une offre « Indeed France » n'a pas sa place sur la page d'une
 * entreprise algérienne), et la page plateforme les liste toutes.
 *
 * @see docs/specifications/MODULE_RECRUITMENT_PUBLIC_PORTAL.md §3.1
 */
final class PublicJobCatalog
{
    /**
     * Offres d'un tenant, éventuellement enrichies des offres agrégées du même
     * pays.
     *
     * @param  array{location?: string|null, contract_type?: string|null, remote_policy?: string|null, q?: string|null}  $filters
     * @return array{tenant: Collection<int, JobPosting>, external: Collection<int, ExternalJobOffer>}
     */
    public function forCompany(Company $company, array $filters, bool $includeExternal = true): array
    {
        $tenant = $this->tenantOffers($company, $filters);

        $external = $includeExternal
            ? $this->externalOffers($filters, $this->countryOf($company))
            : collect();

        return ['tenant' => $tenant, 'external' => $external];
    }

    /**
     * Offres publiées d'un tenant, filtrées.
     *
     * @param  array{location?: string|null, contract_type?: string|null, remote_policy?: string|null, q?: string|null}  $filters
     * @return Collection<int, JobPosting>
     */
    public function tenantOffers(Company $company, array $filters): Collection
    {
        $query = JobPosting::query()
            ->where('company_id', $company->id)
            ->published()
            ->where(function ($q): void {
                $q->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            })
            ->with('department:id,name');

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('published_at')->get();
    }

    /**
     * Offres agrégées visibles, filtrées, éventuellement restreintes à un pays.
     *
     * @param  array{location?: string|null, contract_type?: string|null, remote_policy?: string|null, q?: string|null}  $filters
     * @return Collection<int, ExternalJobOffer>
     */
    public function externalOffers(array $filters, ?string $country = null): Collection
    {
        $query = ExternalJobOffer::query()
            ->visible()
            ->with('source:id,provider,label');

        if ($country !== null && $country !== '') {
            $query->where(function ($q) use ($country): void {
                $q->where('country', $country)->orWhereNull('country');
            });
        }

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('published_at')->limit(200)->get();
    }

    /**
     * Pagination des offres agrégées pour la page plateforme.
     *
     * @param  array{location?: string|null, contract_type?: string|null, remote_policy?: string|null, q?: string|null}  $filters
     * @return LengthAwarePaginator<int, ExternalJobOffer>
     */
    public function paginateExternalOffers(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = ExternalJobOffer::query()
            ->visible()
            ->with('source:id,provider,label');

        $this->applyFilters($query, $filters);

        return $query->orderByDesc('published_at')->paginate($perPage);
    }

    /**
     * Filtres communs aux deux sources. Les colonnes existent des deux côtés
     * (`location`, `contract_type`, `remote_policy`, `title`/`description`),
     * ce qui permet de partager l'implémentation sans branchement.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  array{location?: string|null, contract_type?: string|null, remote_policy?: string|null, q?: string|null}  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['location'])) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }

        if (! empty($filters['contract_type'])) {
            $query->where('contract_type', $filters['contract_type']);
        }

        if (! empty($filters['remote_policy'])) {
            $query->where('remote_policy', $filters['remote_policy']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }
    }

    private function countryOf(Company $company): ?string
    {
        $country = $company->getAttribute('country');

        return is_string($country) && $country !== '' ? strtoupper($country) : null;
    }
}
