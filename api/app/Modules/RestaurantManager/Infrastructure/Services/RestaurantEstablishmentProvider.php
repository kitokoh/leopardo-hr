<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Contracts\Establishments\EstablishmentProvider;
use App\Shared\Establishments\EstablishmentItem;
use Illuminate\Support\Facades\DB;

/**
 * Hub \u00ab Mes \u00e9tablissements \u00bb (#7910) \u2014 provider Restaurant.
 *
 * Un \u00ab \u00e9tablissement \u00bb de la verticale restaurant est une succursale
 * (`restaurant_branches`) : c'est l'unit\u00e9 op\u00e9rationnelle \u00e0 laquelle sont
 * rattach\u00e9s le stock, les rotations, l'\u00e9quipe et les rapports.
 *
 * Isolation tenant : la requ\u00eate filtre EXPLICITEMENT `company_id`. Le hub est
 * une surface authentifi\u00e9e qui n'est PAS derri\u00e8re le scope global tenant de
 * toutes les requ\u00eates brutes \u2014 l'\u00e9crire noir sur blanc rend la contrainte
 * v\u00e9rifiable \u00e0 la lecture, et testable.
 *
 * `staff_count` : nombre d'employ\u00e9s affect\u00e9s \u00e0 la succursale
 * (`restaurant_branch_staff`, #7909), soft-deleted exclus \u2014 un employ\u00e9 retir\u00e9
 * ne compte plus dans l'\u00e9quipe.
 */
final class RestaurantEstablishmentProvider implements EstablishmentProvider
{
    public function vertical(): string
    {
        return 'restaurant';
    }

    /**
     * Fail-closed : sans la feature `restaurantmanager`, la verticale ne
     * d\u00e9clare RIEN, m\u00eame si des succursales subsistent en base (module
     * d\u00e9sactiv\u00e9 \u2192 invisible, crit\u00e8re d'acceptation de #7910).
     */
    public function enabledFor(Company $company): bool
    {
        return $company->hasFeature('restaurantmanager');
    }

    /**
     * @return list<EstablishmentItem>
     */
    public function establishments(Company $company): array
    {
        $table = $this->isPostgres() ? 'shared_tenants.restaurant_branches' : 'restaurant_branches';

        $rows = DB::table($table.' as b')
            ->leftJoin('restaurant_branch_staff as s', function ($join): void {
                $join->on('s.branch_id', '=', 'b.id')
                    ->on('s.company_id', '=', 'b.company_id')
                    ->whereNull('s.deleted_at');
            })
            ->where('b.company_id', $company->id)
            ->groupBy('b.id', 'b.name', 'b.code', 'b.status')
            ->select([
                'b.id as id',
                'b.name as name',
                'b.code as code',
                'b.status as status',
                DB::raw('count(s.id) as staff_count'),
            ])
            ->get();

        $items = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed> $rowArray */
            $rowArray = (array) $row;
            $items[] = $this->toItem($rowArray);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toItem(array $row): EstablishmentItem
    {
        $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
        $name = is_string($row['name'] ?? null) ? $row['name'] : '';
        $code = is_string($row['code'] ?? null) && $row['code'] !== '' ? $row['code'] : null;
        $status = is_string($row['status'] ?? null) ? $row['status'] : 'active';
        $staffCount = is_numeric($row['staff_count'] ?? null) ? (int) $row['staff_count'] : 0;

        return new EstablishmentItem(
            vertical: $this->vertical(),
            id: $id,
            name: $name,
            code: $code,
            status: $status,
            // `?branch=` pr\u00e9-s\u00e9lectionne la succursale dans les pages R\u00c9ELLES de
            // la verticale (`/restaurant/reports`, `/restaurant/team`).
            hrefStats: '/restaurant/reports?branch='.$id,
            hrefTeam: '/restaurant/team?branch='.$id,
            staffCount: $staffCount,
        );
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
