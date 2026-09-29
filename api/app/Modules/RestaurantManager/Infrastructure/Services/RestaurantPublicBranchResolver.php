<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-902 (#7747) — Résolution d'une branche PUBLIQUE par slug pour les
 * surfaces publiques transactionnelles (commande en ligne, avis clients).
 *
 * Mêmes garde-fous fail-closed que l'annuaire RESTO-901
 * (RestaurantPublicDirectoryController::publicBranchesQuery) :
 *  - branche `is_public = true`, active, `public_slug` non nul ;
 *  - société ni suspendue ni expirée, verticale `restaurantmanager` activée ;
 *  - toute condition manquante → 404 uniforme (jamais un 403 qui révélerait
 *    l'existence de la ressource).
 *
 * La résolution traverse les tenants du schéma partagé (`shared_tenants`,
 * tenancy « schema » verrouillée — pattern PlatformMetricsOverview), puis le
 * callback s'exécute DANS le tenant de la branche.
 *
 * BOS-050 (#8208, tranche 7) : garde société et bascule tenant déléguées au
 * socle mutualisé {@see PublicTenantResolver} — `assertAccessible()`
 * (statut + feature `restaurantmanager`, 404 uniforme) et `withinTenant()`
 * (`currentCompany()` posé, scope BelongsToCompany actif, marqueur
 * `tenant_scope_required` restauré en `finally`). La résolution bornée par
 * ressource (slug de branche) reste portée ici, comme prévu par le socle.
 */
final class RestaurantPublicBranchResolver
{
    public function __construct(
        private readonly PublicTenantResolver $publicTenants,
    ) {}

    /**
     * Exécute `$callback(RestaurantBranch $branch)` dans le tenant de la
     * branche publique résolue par `$slug`. 404 fail-closed si le slug est
     * inconnu, dépublié, ou si la société est suspendue/expirée/sans flag.
     *
     * @template T
     *
     * @param  Closure(RestaurantBranch): T  $callback
     * @return T
     */
    public function within(string $slug, Closure $callback): mixed
    {
        $row = DB::table($this->tenantTable('restaurant_branches').' as b')
            ->join('companies as c', 'c.id', '=', 'b.company_id')
            ->where('b.public_slug', $slug)
            ->where('b.is_public', true)
            ->where('b.status', RestaurantRecordStatus::ACTIVE->value)
            ->whereNotIn('c.status', ['suspended', 'expired'])
            ->select(['b.id', 'b.company_id'])
            ->first();

        if ($row === null) {
            abort(404);
        }

        /** @var array<string, mixed> $branchRow */
        $branchRow = get_object_vars($row);
        $companyId = $branchRow['company_id'] ?? null;
        $branchId = $branchRow['id'] ?? null;

        if (! is_scalar($companyId) || ! is_numeric($branchId)) {
            abort(404);
        }

        /** @var Company|null $company */
        $company = Company::query()
            ->where('id', (string) $companyId)
            ->whereNotIn('status', ['suspended', 'expired'])
            ->first();

        if (! $company instanceof Company) {
            abort(404);
        }

        // BOS-050 — garde société mutualisée : statut + feature verticale,
        // 404 uniforme anti-énumération.
        $this->publicTenants->assertAccessible($company, 'restaurantmanager', null, 404);

        return $this->publicTenants->withinTenant($company, function () use ($branchId, $callback): mixed {
            /** @var RestaurantBranch|null $branch */
            $branch = RestaurantBranch::query()
                ->where('id', (int) $branchId)
                ->where('is_public', true)
                ->where('status', RestaurantRecordStatus::ACTIVE)
                ->first();

            if (! $branch instanceof RestaurantBranch) {
                abort(404);
            }

            return $callback($branch);
        });
    }

    private function tenantTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.'.$table : $table;
    }
}
