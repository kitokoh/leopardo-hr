<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-205 (#6018) — Flotte propre de l'agence.
 *
 * Couvre la contrainte `seat_capacity > 0`, la cohérence `carrier_id`
 * nullable et l'isolation cross-tenant.
 */
class TravelVehicleTest extends TestCase
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

    public function test_vehicle_can_be_created_without_carrier(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $vehicle = TravelVehicle::factory()->create(['carrier_id' => null]);

            $this->assertNull($vehicle->refresh()->carrier_id);
        });
    }

    public function test_vehicle_can_be_attached_to_a_carrier(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $carrier = TravelCarrier::factory()->create();
            $vehicle = TravelVehicle::factory()->create(['carrier_id' => $carrier->id]);

            $this->assertSame($carrier->id, $vehicle->refresh()->carrier->id);
        });
    }

    public function test_seat_capacity_must_be_positive(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelVehicle::factory()->create(['seat_capacity' => 0]));
        });
    }

    public function test_vehicles_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelVehicle::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelVehicle::query()->count());
        });
    }

    public function test_vehicle_code_unique_is_tenant_scoped(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelVehicle::factory()->create(['code' => 'VEH-001']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelVehicle::factory()->create(['code' => 'VEH-001']);
            $this->assertSame(1, TravelVehicle::query()->count());
        });
    }
}
