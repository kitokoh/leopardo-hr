<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Geo;

use App\Shared\Contracts\Geo\GeoLocatable;
use App\Shared\Geo\GeoPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * GEO-06 (#8355, BC-33 GEO) — « vue d'adaptation » GeoLocatable des branches
 * restaurant pour le core géospatial.
 *
 * L'annuaire public Restaurant est TRANS-TENANT par conception (surface
 * publique sans auth, schéma partagé `shared_tenants` — voir
 * RestaurantPublicDirectoryController) : cet adaptateur n'a donc PAS le
 * trait BelongsToCompany. À la place, un scope global « annuaire public »
 * reproduit EXACTEMENT l'éligibilité publique (branche publique et active,
 * slug publié, société ni suspendue ni expirée avec le flag
 * `restaurantmanager`) : le type `restaurant` de la registry
 * `geo.searchables` n'expose jamais autre chose que des données déjà
 * publiques (opt-in registry + scope public = fail-closed, spec §7).
 *
 * Les colonnes historiques `latitude`/`longitude` décimales sont lues
 * telles quelles (aucune migration de structure vers geography en v1 —
 * issue dédiée ultérieure, spec §11).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property float|null $latitude
 * @property float|null $longitude
 */
final class RestaurantGeoBranch extends Model implements GeoLocatable
{
    /** @var list<string> */
    private const COMPANY_STATUS_EXCLUDED = ['suspended', 'expired'];

    /**
     * Table tenant qualifiée : les données restaurant vivent dans le schéma
     * partagé `shared_tenants` (tenancy « schema » verrouillée) — miroir de
     * RestaurantPublicDirectoryController::tenantTable().
     */
    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'shared_tenants.restaurant_branches'
            : 'restaurant_branches';
    }

    /**
     * Scope global « annuaire public » : même éligibilité que
     * publicBranchesQuery() — la recherche géographique ne peut jamais
     * retourner une branche qui ne serait pas déjà listée publiquement.
     */
    protected static function booted(): void
    {
        self::addGlobalScope('public_directory', function (Builder $query): void {
            $table = $query->getModel()->getTable();

            $query->where($table.'.is_public', true)
                ->where($table.'.status', 'active')
                ->whereNotNull($table.'.public_slug')
                ->whereExists(function (\Illuminate\Database\Query\Builder $sub) use ($table): void {
                    $sub->selectRaw('1')
                        ->from('companies as c')
                        ->whereColumn('c.id', $table.'.company_id')
                        ->whereNotIn('c.status', self::COMPANY_STATUS_EXCLUDED);

                    if (DB::getDriverName() === 'pgsql') {
                        $sub->whereRaw("coalesce((c.features ->> 'restaurantmanager')::boolean, false) is true");
                    }
                });
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function geoPoint(): ?GeoPoint
    {
        return GeoPoint::fromNullable($this->latitude, $this->longitude);
    }

    public function geoLabel(): string
    {
        return $this->name;
    }

    public static function geoLatitudeColumn(): string
    {
        return 'latitude';
    }

    public static function geoLongitudeColumn(): string
    {
        return 'longitude';
    }
}
