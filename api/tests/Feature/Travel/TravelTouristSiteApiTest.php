<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelTouristSite;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-909 (#6112) — Annuaire des sites touristiques.
 *
 * CRUD, recherche par ville/nom, isolation tenant.
 */
class TravelTouristSiteApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->company = $company;
        $this->tenants = app(TenantManager::class);
    }

    private function actingManager(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    public function test_crud_and_search_by_city(): void
    {
        $this->actingManager();

        $city = $this->tenants->withinTenant($this->company, fn (): TravelCity => TravelCity::factory()->create());

        $this->postJson('/api/v1/travel/tourist-sites', [
            'name' => 'Chutes de la Lobé',
            'description' => 'Cascades au sud du Cameroun',
            'city_id' => $city->id,
            'status' => 'active',
        ])->assertStatus(201);

        $this->getJson('/api/v1/travel/tourist-sites?city_id='.$city->id)
            ->assertOk()
            ->assertJsonFragment(['name' => 'Chutes de la Lobé']);

        $this->getJson('/api/v1/travel/tourist-sites?search=Lob')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Chutes de la Lobé']);

        $site = TravelTouristSite::query()->firstOrFail();

        $this->getJson('/api/v1/travel/tourist-sites/'.$site->id)->assertOk();
        $this->putJson('/api/v1/travel/tourist-sites/'.$site->id, [
            'name' => 'Chutes de la Lobé (rénové)',
        ])->assertOk();

        $this->deleteJson('/api/v1/travel/tourist-sites/'.$site->id)->assertStatus(204);
        self::assertNull(TravelTouristSite::query()->find($site->id));
    }

    public function test_foreign_city_is_rejected(): void
    {
        $this->actingManager();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $foreignCity = $this->tenants->withinTenant($companyB, fn (): TravelCity => TravelCity::factory()->create());

        $this->postJson('/api/v1/travel/tourist-sites', [
            'name' => 'Site avec ville étrangère',
            'city_id' => $foreignCity->id,
        ])->assertStatus(422);
    }

    public function test_sites_are_isolated_per_tenant(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->tenants->withinTenant($companyB, function (): void {
            TravelTouristSite::factory()->create(['name' => 'Site tenant B']);
        });

        $this->actingManager();

        $this->getJson('/api/v1/travel/tourist-sites')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Site tenant B']);
    }

    public function test_cross_tenant_access_is_404(): void
    {
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $siteB = $this->tenants->withinTenant($companyB, fn (): TravelTouristSite => TravelTouristSite::factory()->create());

        $this->actingManager();

        $this->getJson('/api/v1/travel/tourist-sites/'.$siteB->id)->assertStatus(404);
    }
}
