<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\CarrierType;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use Illuminate\Database\QueryException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-204 (#6017) — Compagnies de transport et classes de service.
 *
 * Couvre l'unicité tenant-scoped de `code`, le typage des enums côté PHP,
 * et l'isolation cross-tenant du référentiel.
 */
class TravelCarrierClassTest extends TestCase
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

    public function test_carrier_type_enum_is_cast_correctly(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $carrier = TravelCarrier::factory()->create(['type' => CarrierType::TRAIN->value]);

            $this->assertSame(CarrierType::TRAIN, $carrier->refresh()->type);
        });
    }

    public function test_carriers_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelCarrier::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelCarrier::query()->count());
        });
    }

    public function test_carrier_code_unique_is_tenant_scoped(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelCarrier::factory()->create(['code' => 'CAR-001']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelCarrier::factory()->create(['code' => 'CAR-001']);
            $this->assertSame(1, TravelCarrier::query()->count());
        });
    }

    public function test_carrier_code_unique_within_same_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelCarrier::factory()->create(['code' => 'CAR-DUP']);

            $this->expectException(QueryException::class);
            TravelCarrier::factory()->create(['code' => 'CAR-DUP']);
        });
    }

    public function test_classes_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelClass::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelClass::query()->count());
        });
    }

    public function test_class_code_unique_is_tenant_scoped(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelClass::factory()->create(['code' => 'CLS-001']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelClass::factory()->create(['code' => 'CLS-001']);
            $this->assertSame(1, TravelClass::query()->count());
        });
    }
}
