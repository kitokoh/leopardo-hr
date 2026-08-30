<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-301 (#6031) — GET /travel/countries + GET /travel/cities.
 *
 * Référentiel géo en lecture pour les listes déroulantes : succès, filtres,
 * isolation tenant, 401 sans authentification.
 */
class TravelGeoReadEndpointsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingEmployee(Company $company): Employee
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

    public function test_countries_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/countries')->assertStatus(401);
    }

    public function test_cities_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/cities')->assertStatus(401);
    }

    public function test_countries_lists_tenant_scoped_data(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->actingEmployee($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelCountry::factory()->create(['iso2' => 'CM', 'name' => 'Cameroun']);
        });

        $this->getJson('/api/v1/travel/countries')
            ->assertOk()
            ->assertJsonFragment(['iso2' => 'CM']);
    }

    public function test_countries_search_filter(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->actingEmployee($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelCountry::factory()->create(['iso2' => 'CM', 'name' => 'Cameroun']);
            TravelCountry::factory()->create(['iso2' => 'SN', 'name' => 'Sénégal']);
        });

        $response = $this->getJson('/api/v1/travel/countries?search=Sénégal')->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('SN', $data[0]['iso2']);
    }

    public function test_countries_are_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $companyA->setFeature('travelagency', true);
        $companyA->save();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        app(TenantManager::class)->withinTenant($companyB, function (): void {
            TravelCountry::factory()->create(['iso2' => 'SN']);
        });

        $this->actingEmployee($companyA);

        $this->getJson('/api/v1/travel/countries')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_cities_lists_tenant_scoped_data_with_country_filter(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->actingEmployee($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelCity::factory()->create(['country_iso2' => 'CM', 'name' => 'Douala']);
            TravelCity::factory()->create(['country_iso2' => 'SN', 'name' => 'Dakar']);
        });

        $response = $this->getJson('/api/v1/travel/cities?country_iso2=CM')->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Douala', $data[0]['name']);
    }

    public function test_cities_pagination_per_page_is_bounded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->actingEmployee($company);

        $this->getJson('/api/v1/travel/cities?per_page=5000')
            ->assertStatus(422);
    }
}
