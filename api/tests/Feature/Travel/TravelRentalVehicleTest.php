<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicleImage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-212 (#6025) — Véhicules en location et leurs images.
 *
 * Couvre la contrainte `price_per_day_minor > 0`, l'unicité des positions
 * d'images par véhicule, et l'isolation cross-tenant.
 */
class TravelRentalVehicleTest extends TestCase
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

    public function test_price_per_day_must_be_positive(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelRentalVehicle::factory()->create(['price_per_day_minor' => 0]));
        });
    }

    public function test_vehicles_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelRentalVehicle::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelRentalVehicle::query()->count());
        });
    }

    public function test_code_unique_is_tenant_scoped(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelRentalVehicle::factory()->create(['code' => 'RENT-001']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelRentalVehicle::factory()->create(['code' => 'RENT-001']);
            $this->assertSame(1, TravelRentalVehicle::query()->count());
        });
    }

    public function test_image_position_unique_per_vehicle(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $vehicle = TravelRentalVehicle::factory()->create();
            TravelRentalVehicleImage::factory()->create(['vehicle_id' => $vehicle->id, 'position' => 1]);

            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelRentalVehicleImage::factory()->create(['vehicle_id' => $vehicle->id, 'position' => 1]));
        });
    }

    public function test_same_position_allowed_on_different_vehicles(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $vehicleA = TravelRentalVehicle::factory()->create();
            $vehicleB = TravelRentalVehicle::factory()->create();

            TravelRentalVehicleImage::factory()->create(['vehicle_id' => $vehicleA->id, 'position' => 1]);
            TravelRentalVehicleImage::factory()->create(['vehicle_id' => $vehicleB->id, 'position' => 1]);

            $this->assertSame(2, TravelRentalVehicleImage::query()->count());
        });
    }
}
