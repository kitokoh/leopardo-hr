<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use App\Modules\TravelAgency\Infrastructure\Services\LegacyGeoImportService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1004 (#6117) — Import géographique legacy.
 *
 * Couvre le critère d'acceptation : seed géo PROPRE et REJOUABLE
 * (idempotent, doublons détectés, ISO contrôlés, rapport complet).
 */
class TravelLegacyGeoImportTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function dump(): array
    {
        return [
            'countries' => [
                ['iso2' => 'CM', 'iso3' => 'CMR', 'name' => 'Cameroun', 'phone_code' => 237],
                ['iso2' => 'fr', 'iso3' => 'FRA', 'name' => 'France', 'phone_code' => 33],
                ['iso2' => 'X', 'iso3' => 'BAD', 'name' => 'ISO invalide', 'phone_code' => 0],
            ],
            'cities' => [
                ['country_iso2' => 'CM', 'name' => 'Douala', 'region' => 'Littoral'],
                ['country_iso2' => 'CM', 'name' => 'Yaoundé', 'region' => 'Centre'],
                ['country_iso2' => 'CM', 'name' => 'Douala', 'region' => 'Littoral'], // doublon
                ['country_iso2' => 'ZZ', 'name' => 'Orphelin', 'region' => null],
            ],
        ];
    }

    public function test_geo_seed_is_clean_and_replayable(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $service = app(LegacyGeoImportService::class);

        $first = app(TenantManager::class)->withinTenant($company, fn () => $service->import($company->id, $this->dump()));
        $second = app(TenantManager::class)->withinTenant($company, fn () => $service->import($company->id, $this->dump()));

        // Rejeu : aucun nouvel enregistrement (seed rejouable).
        $this->assertSame(0, $second['cities']);

        // Rapport : 2 pays valides (1 ISO invalide sauté), 2 villes uniques
        // (1 doublon + 1 orpheline sautés).
        $this->assertSame(2, $first['countries']);
        $this->assertSame(2, $first['cities']);
        $this->assertNotEmpty($first['skipped']);

        // Rejeu : aucun doublon en base.
        $this->assertSame(2, TravelCountry::query()->where('company_id', $company->id)->count());
        $this->assertSame(2, TravelCity::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, TravelCity::query()->where('company_id', $company->id)->where('name', 'Douala')->count());

        // Normalisation ISO (minuscules → majuscules).
        $this->assertSame(1, TravelCountry::query()->where('iso2', 'FR')->count());
    }

    public function test_geo_dry_run_writes_nothing(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $report = app(TenantManager::class)->withinTenant($company, fn () => app(LegacyGeoImportService::class)->import($company->id, $this->dump(), dryRun: true));

        $this->assertSame(3, $report['countries']);
        $this->assertSame(4, $report['cities']);
        $this->assertSame(0, TravelCountry::query()->count());
    }
}
