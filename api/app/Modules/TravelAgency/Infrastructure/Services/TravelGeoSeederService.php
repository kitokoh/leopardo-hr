<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\TravelRecordStatus;
use App\Modules\TravelAgency\Infrastructure\Data\Cities;
use App\Modules\TravelAgency\Infrastructure\Data\Countries;
use Illuminate\Support\Facades\DB;

/**
 * Seed du référentiel géographique tenant-scoped (TRAVEL-202, issue #6015).
 *
 * Charge les pays (ISO 3166-1, 223 entrées) et les villes principales dans le
 * contexte du tenant : `insertOrIgnore` sur la clé unique (company_id, iso2)
 * pour les pays et sur (company_id, country_iso2, name) pour les villes.
 *
 * Idempotence : rejouer le seed ne crée jamais de doublon et ne réécrit jamais
 * les modifications apportées par le tenant (statuts, nouvelles villes).
 */
final class TravelGeoSeederService
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function seed(Company $company): void
    {
        $this->tenants->withinTenant($company, function (): void {
            $this->seedCountries();
            $this->seedCities();
        });
    }

    private function seedCountries(): void
    {
        $rows = [];
        foreach (Countries::all() as $iso2 => $country) {
            $rows[] = [
                'company_id' => currentCompany()->id,
                'iso2' => $iso2,
                'iso3' => $country['iso3'],
                'name' => $country['name'],
                'phone_code' => $country['phone_code'],
                'status' => TravelRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('travel_countries')->insertOrIgnore($chunk);
        }
    }

    private function seedCities(): void
    {
        $rows = [];
        foreach (Cities::all() as $city) {
            $rows[] = [
                'company_id' => currentCompany()->id,
                'country_iso2' => $city['iso2'],
                'name' => $city['name'],
                'region' => $city['region'],
                'latitude' => $city['latitude'],
                'longitude' => $city['longitude'],
                'status' => TravelRecordStatus::ACTIVE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('travel_cities')->insertOrIgnore($chunk);
        }
    }
}
