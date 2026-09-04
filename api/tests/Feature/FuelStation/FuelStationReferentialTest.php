<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6712 — le dashboard admin « Fuel stations » appelle 3 endpoints
 * (stations, incidents, reconciliations) qui n'existaient pas → 3 toasts
 * d'erreur permanents. Le référentiel read-only est désormais servi sur les
 * VRAIES tables fuel.
 */
class FuelStationReferentialTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'timezone' => 'Africa/Algiers',
        ]);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;

        DB::statement('SET search_path TO shared_tenants,public');
    }

    public function test_stations_endpoint_lists_fuel_stations(): void
    {
        DB::table('fuel_stations')->insert([
            'company_id' => $this->company->id,
            'code' => 'ST-001',
            'name' => 'Station Alger Centre',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/fuel-station/stations?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'ST-001')
            ->assertJsonPath('data.0.name', 'Station Alger Centre')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_incidents_endpoint_derives_from_inactive_equipment(): void
    {
        $stationId = DB::table('fuel_stations')->insertGetId([
            'company_id' => $this->company->id,
            'code' => 'ST-002',
            'name' => 'Station Oran',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fuel_pumps')->insert([
            'company_id' => $this->company->id,
            'station_id' => $stationId,
            'code' => 'P-01',
            'status' => 'retired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/fuel-station/incidents?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.equipment_type', 'pump')
            ->assertJsonPath('data.0.priority', 'high');
    }

    public function test_reconciliations_endpoint_maps_pending_review(): void
    {
        $stationId = DB::table('fuel_stations')->insertGetId([
            'company_id' => $this->company->id,
            'code' => 'ST-003',
            'name' => 'Station Blida',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fuel_cash_sessions')->insert([
            'company_id' => $this->company->id,
            'station_id' => $stationId,
            'opened_by' => $this->manager->id,
            'opened_at' => now()->subDay(),
            'closed_at' => now(),
            'opening_balance' => 1000,
            'closing_balance' => 1500,
            'expected_balance' => 1490,
            'variance' => 10,
            'status' => 'closed',
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->manager)
            ->getJson('/api/v1/fuel-station/reconciliations?status=pending_review&per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'closed')
            ->assertJsonPath('data.0.variance', '10.00');
    }

    public function test_referential_requires_manager_role(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $this->actingAs($employee)
            ->getJson('/api/v1/fuel-station/stations')
            ->assertForbidden();
    }
}
