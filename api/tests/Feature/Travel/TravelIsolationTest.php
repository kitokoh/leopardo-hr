<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-108 (#6013) — Isolation tenant du référentiel TravelAgency.
 *
 * Chaque table métier porte `company_id` ; le scope BelongsToCompany filtre
 * les lectures par le tenant courant (TenantManager::withinTenant). Un tenant
 * ne voit jamais les données d'un autre tenant, et les clés d'unicité sont
 * tenant-scoped (deux tenants peuvent avoir le même iso2 / code de gare).
 */
class TravelIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyB = $companyB;

        $this->tenants = app(TenantManager::class);
    }

    public function test_countries_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelCountry::factory()->create(['iso2' => 'CM']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelCountry::query()->count());
            TravelCountry::factory()->create(['iso2' => 'CM']);
            $this->assertSame(1, TravelCountry::query()->count());
        });

        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->assertSame(1, TravelCountry::query()->count());
        });
    }

    public function test_same_iso2_is_allowed_across_tenants(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelCountry::factory()->create(['iso2' => 'CM']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelCountry::factory()->create(['iso2' => 'CM']);
            $this->assertSame(1, TravelCountry::query()->count());
        });
    }

    public function test_cities_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelCity::factory()->create(['name' => 'Douala']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelCity::query()->count());
            TravelCity::factory()->create(['name' => 'Douala']);
            $this->assertSame(1, TravelCity::query()->count());
        });
    }

    public function test_station_code_unique_is_tenant_scoped(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelStation::factory()->create(['code' => 'GAR-001']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelStation::factory()->create(['code' => 'GAR-001']);
            $this->assertSame(1, TravelStation::query()->count());
        });
    }

    public function test_offices_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelOffice::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelOffice::query()->count());
        });
    }
}
