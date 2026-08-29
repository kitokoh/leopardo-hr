<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use Illuminate\Support\Facades\DB;

/**
 * Seed de données démonstratives pour la verticale TravelAgency (TRAVEL-107,
 * issue #6012).
 *
 * Génère un jeu de données synthétiques NON SENSIBLES pour un tenant pilote :
 *   - référentiel géographique (via TravelGeoSeederService) ;
 *   - gares et bureaux de vente de démonstration ;
 *   - (à venir avec TRAVEL-204/207/209 : classes, route + étapes, trajets,
 *     tarifs et réservation confirmée — voir spec §12, lot 2xx/3xx).
 *
 * Idempotence : les insertions utilisent insertOrIgnore sur les clés uniques
 * tenant-scoped ; rejouer la commande ne crée jamais de doublon.
 */
final class TravelDemoSeederService
{
    public function __construct(private readonly TravelGeoSeederService $geoSeeder)
    {
    }

    public function seed(Company $company): void
    {
        $this->geoSeeder->seed($company);

        $this->seedDemoStationsAndOffices($company);
    }

    private function seedDemoStationsAndOffices(Company $company): void
    {
        DB::transaction(function () use ($company): void {
            $cityIds = $this->cityIdsByCountry($company, ['CM', 'CI', 'SN']);

            $stations = [
                ['code' => 'DLA-CENTRE', 'name' => 'Gare Routière de Douala', 'country' => 'CM', 'terminal' => true],
                ['code' => 'YDE-MVOGBA', 'name' => 'Gare de Mvog-Ada (Yaoundé)', 'country' => 'CM', 'terminal' => true],
                ['code' => 'ABJ-ADJAME', 'name' => 'Gare d\'Adjamé (Abidjan)', 'country' => 'CI', 'terminal' => true],
                ['code' => 'DKR-POMPIERS', 'name' => 'Gare des Pompiers (Dakar)', 'country' => 'SN', 'terminal' => true],
            ];

            $stationRows = [];
            foreach ($stations as $station) {
                $cityId = $cityIds[$station['country']] ?? null;
                if ($cityId === null) {
                    continue;
                }

                $stationRows[] = [
                    'company_id' => $company->id,
                    'code' => $station['code'],
                    'name' => $station['name'],
                    'city_id' => $cityId,
                    'timezone' => 'Africa/Douala',
                    'is_terminal' => $station['terminal'],
                    'status' => TravelRecordStatus::ACTIVE->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($stationRows, 100) as $chunk) {
                DB::table('travel_stations')->insertOrIgnore($chunk);
            }

            $officeRows = [];
            foreach (['Bureau Centre-ville Douala', 'Bureau Centre-ville Yaoundé'] as $name) {
                $cityId = $cityIds['CM'] ?? null;
                if ($cityId === null) {
                    continue;
                }

                $officeRows[] = [
                    'company_id' => $company->id,
                    'name' => $name,
                    'city_id' => $cityId,
                    'status' => TravelRecordStatus::ACTIVE->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($officeRows, 100) as $chunk) {
                DB::table('travel_offices')->insertOrIgnore($chunk);
            }
        });
    }

    /**
     * Première ville seedée d'un pays donné — pour la démo, n'importe quelle
     * ville du pays convient.
     *
     * @return array<string, int>
     */
    private function cityIdsByCountry(Company $company, array $iso2s): array
    {
        /** @var \Illuminate\Support\Collection<string, int> $idsByCountry */
        $idsByCountry = DB::table('travel_cities')
            ->where('company_id', $company->id)
            ->whereIn('country_iso2', $iso2s)
            ->orderBy('id')
            ->pluck('id', 'country_iso2');

        $map = [];
        foreach ($iso2s as $iso2) {
            if ($idsByCountry->has($iso2)) {
                $map[$iso2] = (int) $idsByCountry->get($iso2);
            }
        }

        return $map;
    }
}
