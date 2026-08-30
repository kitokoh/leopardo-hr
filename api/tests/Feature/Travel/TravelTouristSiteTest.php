<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;
use App\Core\Tenant\TenantManager;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-909 (#6112) — Annuaire des sites touristiques : CRUD tenant-scoped,
 * recherche par ville (annuaire consultable), géo bornée, isolation
 * cross-tenant.
 */
class TravelTouristSiteTest extends TestCase
{
    use RefreshTenantDatabase;

    private function login(Company $company, string $role = 'manager', ?string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function makeCity(Company $company, string $name = 'Yaoundé'): TravelCity
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($name): TravelCity {
            TravelCountry::query()->create([
                'company_id' => $company->id,
                'iso2' => 'CM',
                'name' => 'Cameroun',
            ]);

            return TravelCity::query()->create([
                'company_id' => $company->id,
                'country_iso2' => 'CM',
                'name' => $name,
            ]);
        });
    }

    public function test_site_crud_and_search_by_city(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);
        $city = $this->makeCity($company);

        $created = $this->postJson('/api/v1/travel/tourist-sites', [
            'name' => 'Chutes de la Lobé',
            'description_redacted' => 'Cascades',
            'city_id' => $city->id,
            'latitude' => 2.8765,
            'longitude' => 9.9000,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'published');

        $siteId = (int) $created->json('data.id');

        // Recherche par ville.
        $this->getJson('/api/v1/travel/tourist-sites?city_id='.$city->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Recherche par nom (insensible à la casse).
        $this->getJson('/api/v1/travel/tourist-sites?search=lobé')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/travel/tourist-sites?search=inexistant')->assertJsonCount(0, 'data');

        // Mise à jour.
        $this->putJson("/api/v1/travel/tourist-sites/{$siteId}", ['status' => 'archived'])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->deleteJson("/api/v1/travel/tourist-sites/{$siteId}")->assertStatus(204);
    }

    public function test_site_geo_and_city_are_validated(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company);

        // Latitude hors bornes → 422.
        $this->postJson('/api/v1/travel/tourist-sites', ['name' => 'X', 'latitude' => 91])
            ->assertStatus(422);

        // Ville d'un autre tenant → 422.
        $foreignCity = $this->makeCity($other, 'Douala');
        $this->postJson('/api/v1/travel/tourist-sites', ['name' => 'X', 'city_id' => $foreignCity->id])
            ->assertStatus(422);

        // Filtre par ville d'un autre tenant → 422.
        $this->getJson('/api/v1/travel/tourist-sites?city_id='.$foreignCity->id)->assertStatus(422);
    }

    public function test_site_is_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->activateTravel($companyB);

        $this->login($companyA);
        $site = app(TenantManager::class)->withinTenant($companyA, fn () => TravelTouristSite::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Mont Cameroun',
            'status' => 'published',
        ]));

        $this->login($companyB);
        $this->getJson("/api/v1/travel/tourist-sites/{$site->id}")->assertStatus(404);
        $this->getJson('/api/v1/travel/tourist-sites')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_write_requires_operational_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->login($company, role: 'employee', managerRole: null);

        $this->postJson('/api/v1/travel/tourist-sites', ['name' => 'X'])->assertStatus(403);
    }
}
