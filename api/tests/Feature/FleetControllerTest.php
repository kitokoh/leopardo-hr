<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\TraccarService;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleAlert;
use App\Modules\Fleet\Domain\Models\VehicleMaintenance;
use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class FleetControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_manager_can_read_fleet_overview_for_own_company_only(): void
    {
        [$company, $manager] = $this->fleetActor();
        $otherCompany = Company::factory()->create();

        $active = $this->vehicle($company, ['plate_number' => 'DZ-ACTIVE', 'status' => 'active']);
        $this->vehicle($company, ['plate_number' => 'DZ-MAINT', 'status' => 'maintenance']);
        $this->vehicle($company, ['plate_number' => 'DZ-OLD', 'status' => 'decommissioned']);
        $this->vehicle($otherCompany, ['plate_number' => 'FR-OTHER', 'status' => 'active']);

        VehicleAlert::query()->create([
            'company_id' => $company->id,
            'vehicle_id' => $active->id,
            'type' => 'overspeed',
            'message' => 'Speed threshold exceeded',
            'acknowledged' => false,
        ]);
        VehicleAlert::query()->create([
            'company_id' => $otherCompany->id,
            'vehicle_id' => $this->vehicle($otherCompany)->id,
            'type' => 'overspeed',
            'message' => 'Other tenant alert',
            'acknowledged' => false,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/overview');

        $response->assertOk()
            ->assertJsonPath('data.total_vehicles', 3)
            ->assertJsonPath('data.active', 1)
            ->assertJsonPath('data.in_maintenance', 1)
            ->assertJsonPath('data.decommissioned', 1)
            ->assertJsonPath('data.unacknowledged_alerts', 1);
    }

    public function test_live_map_uses_traccar_contract_without_external_http(): void
    {
        [$company, $manager] = $this->fleetActor();
        $tracked = $this->vehicle($company, [
            'plate_number' => 'DZ-TRACK',
            'status' => 'active',
            'traccar_device_id' => 42,
        ]);
        $this->vehicle($company, [
            'plate_number' => 'DZ-NO-TRACK',
            'status' => 'active',
            'traccar_device_id' => null,
        ]);
        $this->fakeTraccar([
            42 => ['latitude' => 36.7538, 'longitude' => 3.0588, 'speed' => 32],
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/live-map');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vehicle_id', $tracked->id)
            ->assertJsonPath('data.0.plate_number', 'DZ-TRACK')
            ->assertJsonPath('data.0.position.latitude', 36.7538)
            ->assertJsonPath('data.0.position.longitude', 3.0588);
    }

    public function test_live_map_fails_open_when_traccar_token_not_configured(): void
    {
        [$company, $manager] = $this->fleetActor();
        $this->vehicle($company, [
            'plate_number' => 'DZ-TRACK',
            'status' => 'active',
            'traccar_device_id' => 42,
        ]);

        // QA pass #2175 : sans TRACCAR_API_TOKEN, config('tracking.traccar_token')
        // vaut null → TypeError « Cannot assign null to property
        // TraccarService::$token of type string » (500 sur /fleet/live-map).
        // Le tracking est optionnel : fail-open avec des données vides.
        config(['tracking.traccar_token' => null]);
        $this->app->forgetInstance(TraccarService::class);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fleet/live-map')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plate_number', 'DZ-TRACK')
            ->assertJsonPath('data.0.position', null);
    }

    public function test_fuel_and_mileage_reports_are_grouped_by_vehicle(): void
    {
        [$company, $manager] = $this->fleetActor();
        $vehicle = $this->vehicle($company, ['plate_number' => 'DZ-REPORT']);

        VehicleTrip::query()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'start_time' => '2026-05-05 08:00:00',
            'distance_km' => 120.5,
            'avg_speed_kmh' => 60,
            'fuel_consumed' => 12.3,
        ]);
        VehicleTrip::query()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'start_time' => '2026-05-06 08:00:00',
            'distance_km' => 80.5,
            'avg_speed_kmh' => 50,
            'fuel_consumed' => 7.7,
        ]);

        Sanctum::actingAs($manager);

        $fuel = $this->getJson('/api/v1/fleet/reports/fuel?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('data.0.vehicle_id', $vehicle->id)
            ->json('data.0');
        $this->assertEqualsWithDelta(20.0, (float) $fuel['total_fuel'], 0.01);
        $this->assertEqualsWithDelta(201.0, (float) $fuel['total_distance'], 0.01);

        $mileage = $this->getJson('/api/v1/fleet/reports/mileage?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('data.0.vehicle_id', $vehicle->id)
            ->assertJsonPath('data.0.trip_count', 2)
            ->json('data.0');
        $this->assertEqualsWithDelta(201.0, (float) $mileage['total_km'], 0.01);
        $this->assertEqualsWithDelta(55.0, (float) $mileage['avg_speed'], 0.01);
    }

    public function test_maintenance_due_returns_only_next_30_days_for_current_company(): void
    {
        [$company, $manager] = $this->fleetActor();
        $vehicle = $this->vehicle($company, ['plate_number' => 'DZ-SERVICE']);
        $laterVehicle = $this->vehicle($company, ['plate_number' => 'DZ-LATER']);
        $otherVehicle = $this->vehicle(Company::factory()->create(), ['plate_number' => 'FR-OTHER']);

        VehicleMaintenance::query()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'oil_change',
            'next_service_date' => now()->addDays(10)->toDateString(),
        ]);
        VehicleMaintenance::query()->create([
            'company_id' => $company->id,
            'vehicle_id' => $laterVehicle->id,
            'type' => 'technical_control',
            'next_service_date' => now()->addDays(45)->toDateString(),
        ]);
        VehicleMaintenance::query()->create([
            'company_id' => $otherVehicle->company_id,
            'vehicle_id' => $otherVehicle->id,
            'type' => 'oil_change',
            'next_service_date' => now()->addDays(5)->toDateString(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/fleet/reports/maintenance-due');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vehicle.plate_number', 'DZ-SERVICE')
            ->assertJsonPath('data.0.type', 'oil_change');
    }

    public function test_fleet_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/fleet/overview')->assertUnauthorized();
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function fleetActor(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $manager];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function vehicle(Company $company, array $overrides = []): Vehicle
    {
        return Vehicle::query()->create(array_merge([
            'company_id' => $company->id,
            'plate_number' => 'DZ-'.fake()->unique()->bothify('####-??'),
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'type' => 'van',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ], $overrides));
    }

    /**
     * @param  array<int, array<string, mixed>>  $positions
     */
    private function fakeTraccar(array $positions): void
    {
        $this->app->instance(TraccarService::class, new class($positions) extends TraccarService
        {
            /**
             * @param  array<int, array<string, mixed>>  $positions
             */
            public function __construct(private readonly array $positions) {}

            /**
             * @return array<string, mixed>|null
             */
            public function getLastPosition(int $deviceId): ?array
            {
                return $this->positions[$deviceId] ?? null;
            }

            /**
             * getLastPositions() a été ajoutée au service par e3525f078
             * (#3148, N+1 live-map) — sans override, le fake hérite de la
             * méthode réelle qui lit $this->token (jamais initialisé : le
             * constructeur du fake ne touche pas parent::__construct) →
             * 500 « must not be accessed before initialization » (issue #5201).
             *
             * @param  list<int>  $deviceIds
             * @return array<int, array<string, mixed>|null>
             */
            public function getLastPositions(array $deviceIds): array
            {
                $result = [];
                foreach ($deviceIds as $deviceId) {
                    $result[$deviceId] = $this->positions[$deviceId] ?? null;
                }

                return $result;
            }
        });
    }

    /**
     * Sécurité #2217 — RBAC du module Fleet.
     */
    public function test_employee_cannot_list_or_write_vehicles(): void
    {
        [$company] = $this->fleetActor();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $vehicle = $this->vehicle($company);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/vehicles')->assertForbidden();
        $this->getJson('/api/v1/vehicles/'.$vehicle->id)->assertForbidden();
        $this->postJson('/api/v1/vehicles', ['plate_number' => 'DZ-EMP-1', 'name' => 'x'])->assertForbidden();
        $this->putJson('/api/v1/vehicles/'.$vehicle->id, ['status' => 'decommissioned'])->assertForbidden();
        $this->deleteJson('/api/v1/vehicles/'.$vehicle->id)->assertForbidden();
        $this->postJson('/api/v1/vehicles/'.$vehicle->id.'/assign', ['employee_id' => $employee->id])->assertForbidden();
    }

    public function test_employee_cannot_access_fleet_dashboard_or_live_map(): void
    {
        [$company] = $this->fleetActor();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/fleet/overview')->assertForbidden();
        $this->getJson('/api/v1/fleet/live-map')->assertForbidden();
        $this->getJson('/api/v1/fleet/reports/fuel')->assertForbidden();
        $this->getJson('/api/v1/vehicle-alerts')->assertForbidden();
        $this->getJson('/api/v1/vehicle-trips')->assertForbidden();
        $this->getJson('/api/v1/vehicle-maintenance')->assertForbidden();
    }

    public function test_employee_cannot_read_position_of_unassigned_vehicle(): void
    {
        [$company] = $this->fleetActor();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $vehicle = $this->vehicle($company, ['traccar_device_id' => 42]);

        $this->fakeTraccar([42 => ['latitude' => 36.7, 'longitude' => 3.0, 'speed' => 12.5]]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/vehicles/'.$vehicle->id.'/position')->assertForbidden();
    }

    public function test_employee_can_read_position_of_own_vehicle(): void
    {
        [$company] = $this->fleetActor();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $vehicle = $this->vehicle($company, [
            'traccar_device_id' => 42,
            'assigned_driver_id' => $employee->id,
        ]);

        $this->fakeTraccar([42 => ['latitude' => 36.7, 'longitude' => 3.0, 'speed' => 12.5]]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/vehicles/'.$vehicle->id.'/position')
            ->assertOk()
            ->assertJsonPath('data.latitude', 36.7);
    }

    public function test_my_vehicles_returns_only_assigned_vehicles(): void
    {
        [$company] = $this->fleetActor();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $assigned = $this->vehicle($company, [
            'traccar_device_id' => 42,
            'assigned_driver_id' => $employee->id,
        ]);
        $this->vehicle($company, ['traccar_device_id' => 43]);

        $this->fakeTraccar([42 => ['latitude' => 36.7, 'longitude' => 3.0, 'speed' => 12.5, 'fixTime' => '2026-08-14T10:00:00Z']]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/vehicles')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($assigned->id, $response->json('data.0.vehicle_id'));
        $this->assertEquals(36.7, $response->json('data.0.latitude'));
        $this->assertEquals(3.0, $response->json('data.0.longitude'));
    }

    public function test_manager_keeps_full_fleet_access(): void
    {
        [$company, $manager] = $this->fleetActor();
        $vehicle = $this->vehicle($company, ['traccar_device_id' => 42]);
        $this->fakeTraccar([42 => ['latitude' => 36.7, 'longitude' => 3.0]]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/vehicles')->assertOk();
        $this->getJson('/api/v1/fleet/overview')->assertOk();
        $this->getJson('/api/v1/fleet/live-map')->assertOk();
        $this->getJson('/api/v1/vehicles/'.$vehicle->id.'/position')->assertOk();
    }
}
