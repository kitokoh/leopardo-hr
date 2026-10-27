<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Contracts\Establishments\EstablishmentProvider;
use App\Shared\Establishments\EstablishmentItem;
use Illuminate\Support\Facades\DB;

/**
 * Hub \u00ab Mes \u00e9tablissements \u00bb (#7910) \u2014 provider FuelStation.
 *
 * Un \u00ab \u00e9tablissement \u00bb fuel est une STATION (`fuel_stations`), pas un
 * `fuel_sites` : la station est le site op\u00e9rationnel de premier niveau
 * (celui que le tenant choisit en se connectant), les `fuel_sites` en sont
 * des subdivisions internes (#5796).
 *
 * `staff_count` : employ\u00e9s affect\u00e9s \u00e0 un shift de la station
 * (`fuel_shift_assignments` \u2192 `fuel_shifts.station_id`). On compte les
 * couples (employ\u00e9, station) distincts : un employ\u00e9 affect\u00e9 \u00e0 3 shifts de la
 * m\u00eame station reste UNE personne dans l'\u00e9quipe.
 *
 * Les affectations `cancelled` sont exclues \u2014 une annulation n'est pas une
 * pr\u00e9sence, et les compter gonflerait l'effectif affich\u00e9.
 */
final class FuelEstablishmentProvider implements EstablishmentProvider
{
    public function vertical(): string
    {
        return 'fuel';
    }

    /**
     * Fail-closed : le flag de la verticale est `fuel_station` c\u00f4t\u00e9
     * `client-features.ts` (`featureKeys: ['fuel_station', 'fuel']`).
     */
    public function enabledFor(Company $company): bool
    {
        return $company->hasFeature('fuel_station');
    }

    /**
     * @return list<EstablishmentItem>
     */
    public function establishments(Company $company): array
    {
        $stationsTable = $this->isPostgres() ? 'shared_tenants.fuel_stations' : 'fuel_stations';

        $rows = DB::table($stationsTable.' as st')
            ->leftJoin('fuel_shifts as sh', function ($join): void {
                $join->on('sh.station_id', '=', 'st.id')
                    ->on('sh.company_id', '=', 'st.company_id');
            })
            ->leftJoin('fuel_shift_assignments as a', function ($join): void {
                $join->on('a.shift_id', '=', 'sh.id')
                    ->on('a.company_id', '=', 'st.company_id')
                    ->where('a.status', '<>', 'cancelled')
                    ->whereColumn('a.assignment_date', '>=', DB::raw('current_date - interval \'90 days\''));
            })
            ->where('st.company_id', $company->id)
            ->groupBy('st.id', 'st.name', 'st.code', 'st.status')
            ->select([
                'st.id as id',
                'st.name as name',
                'st.code as code',
                'st.status as status',
                DB::raw('count(distinct a.employee_id) as staff_count'),
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
            // Pages R\u00c9ELLES de la verticale : `/fuel/sales` (ventes par station)
            // et `/fuel/team` (affectations de shifts).
            hrefStats: '/fuel/sales?station='.$id,
            hrefTeam: '/fuel/team?station='.$id,
            staffCount: $staffCount,
        );
    }

    private function isPostgres(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }
}
