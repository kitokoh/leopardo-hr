<?php

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\TraccarService;
use App\Modules\Fleet\Application\Actions\AcknowledgeVehicleAlertAction;
use App\Modules\Fleet\Application\Actions\AggregateFleetOverviewAction;
use App\Modules\Fleet\Application\Actions\AssignDriverToVehicleAction;
use App\Modules\Fleet\Application\Actions\BuildFleetLiveMapAction;
use App\Modules\Fleet\Application\Actions\DeleteVehicleAction;
use App\Modules\Fleet\Application\Actions\DeleteVehicleMaintenanceAction;
use App\Modules\Fleet\Application\Actions\ListVehiclesDueForMaintenanceAction;
use App\Modules\Fleet\Application\Actions\RecordVehicleMaintenanceAction;
use App\Modules\Fleet\Application\Actions\RegisterVehicleAction;
use App\Modules\Fleet\Application\Actions\ReportVehicleFuelConsumptionAction;
use App\Modules\Fleet\Application\Actions\ReportVehicleMileageAction;
use App\Modules\Fleet\Application\Actions\UnassignDriverFromVehicleAction;
use App\Modules\Fleet\Application\Actions\UpdateVehicleAction;
use App\Modules\Fleet\Application\Actions\UpdateVehicleMaintenanceAction;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleAssignment;
use App\Modules\Fleet\Domain\Models\VehicleMaintenance;
use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Tests des Actions de la couche Application Fleet — BOS-024g
 * (#8218, BC-24).
 *
 * Chaque Action est éprouvée directement (conteneur) sur ses
 * responsabilités propres : scoping tenant explicite (`company_id` issu de
 * la session, jamais du payload client), cycle de vie des affectations
 * (ouverture/fermeture, conducteur courant), acquittement d'alerte,
 * agrégats du tableau de bord, contrat d'appel Traccar agrégé du live-map
 * (#3148), rapports carburant/kilométrage et fenêtre d'échéances de
 * maintenance. La parité HTTP reste couverte par les suites de contrat
 * existantes (FleetControllerTest, VehicleResourceContractTest), vertes
 * sans modification — preuve d'absence de changement d'API.
 */
class FleetApplicationActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $driverA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        $this->companyB = $companyB;

        /** @var Employee $driverA */
        $driverA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->driverA = $driverA;
    }

    // ── Fixtures ────────────────────────────────────────────────────────

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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function vehiclePayload(array $overrides = []): array
    {
        return array_merge([
            'plate_number' => 'DZ-1234-AB',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'type' => 'van',
            'fuel_type' => 'diesel',
            'status' => 'active',
            'mileage' => 12000,
        ], $overrides);
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

    // ── Véhicules ───────────────────────────────────────────────────────

    public function test_register_action_uses_company_from_session_not_payload(): void
    {
        $vehicle = app(RegisterVehicleAction::class)->execute(
            (string) $this->companyA->id,
            // Un tenant forgé dans le payload ne doit jamais gagner.
            $this->vehiclePayload(['company_id' => (string) $this->companyB->id]),
        );

        $this->assertSame((string) $this->companyA->id, $vehicle->company_id);
        $this->assertSame('DZ-1234-AB', $vehicle->plate_number);
        $this->assertSame(12000, (int) $vehicle->mileage);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'company_id' => (string) $this->companyA->id,
        ]);
    }

    public function test_update_action_persists_partial_payload_and_returns_fresh_model(): void
    {
        $vehicle = $this->vehicle($this->companyA, ['status' => 'active', 'mileage' => 1000]);

        $updated = app(UpdateVehicleAction::class)->execute($vehicle, [
            'status' => 'maintenance',
            'mileage' => 4500,
        ]);

        $this->assertSame('maintenance', $updated->status);
        $this->assertSame(4500, (int) $updated->mileage);
        $this->assertSame((string) $vehicle->plate_number, (string) $updated->plate_number);
        $this->assertSame((string) $this->companyA->id, $updated->company_id);
    }

    public function test_delete_action_removes_vehicle(): void
    {
        $vehicle = $this->vehicle($this->companyA);

        app(DeleteVehicleAction::class)->execute($vehicle);

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    // ── Affectations ────────────────────────────────────────────────────

    public function test_assign_action_journals_assignment_and_sets_current_driver(): void
    {
        $vehicle = $this->vehicle($this->companyA);

        $assignment = app(AssignDriverToVehicleAction::class)->execute(
            $vehicle,
            (string) $this->companyA->id,
            (int) $this->driverA->id,
            '2026-10-01',
            'Remplacement congés',
            (int) $this->driverA->id,
        );

        $this->assertSame((int) $this->driverA->id, (int) $assignment->employee_id);
        $this->assertSame((string) $this->companyA->id, (string) $assignment->company_id);
        $this->assertSame('Remplacement congés', $assignment->reason);
        $this->assertNull($assignment->end_date);
        $this->assertSame((int) $this->driverA->id, (int) $vehicle->refresh()->assigned_driver_id);
    }

    public function test_unassign_action_closes_open_assignment_and_clears_driver(): void
    {
        $vehicle = $this->vehicle($this->companyA);
        $assign = app(AssignDriverToVehicleAction::class);

        $assign->execute($vehicle, (string) $this->companyA->id, (int) $this->driverA->id, '2026-10-01');
        app(UnassignDriverFromVehicleAction::class)->execute($vehicle->refresh());

        /** @var VehicleAssignment $assignment */
        $assignment = VehicleAssignment::query()->where('vehicle_id', $vehicle->id)->firstOrFail();

        $this->assertNotNull($assignment->end_date);
        $this->assertNull($vehicle->refresh()->assigned_driver_id);
    }

    public function test_unassign_action_is_a_noop_without_open_assignment(): void
    {
        $vehicle = $this->vehicle($this->companyA);

        app(UnassignDriverFromVehicleAction::class)->execute($vehicle);

        $this->assertSame(0, VehicleAssignment::query()->where('vehicle_id', $vehicle->id)->count());
        $this->assertNull($vehicle->refresh()->assigned_driver_id);
    }

    // ── Alertes ─────────────────────────────────────────────────────────

    public function test_acknowledge_action_marks_alert_with_author(): void
    {
        $vehicle = $this->vehicle($this->companyA);

        $alert = $vehicle->alerts()->create([
            'company_id' => (string) $this->companyA->id,
            'type' => 'speeding',
            'message' => 'Speed threshold exceeded',
            'acknowledged' => false,
        ]);

        $acknowledged = app(AcknowledgeVehicleAlertAction::class)
            ->execute($alert, (int) $this->driverA->id);

        $this->assertTrue((bool) $acknowledged->acknowledged);
        $this->assertSame((int) $this->driverA->id, (int) $acknowledged->acknowledged_by);
    }

    // ── Tableau de bord et carte temps réel ─────────────────────────────

    public function test_overview_aggregates_only_current_tenant(): void
    {
        $active = $this->vehicle($this->companyA, ['status' => 'active']);
        $this->vehicle($this->companyA, ['status' => 'maintenance']);
        $this->vehicle($this->companyA, ['status' => 'decommissioned']);
        $this->vehicle($this->companyB, ['status' => 'active']);

        $active->alerts()->create([
            'company_id' => (string) $this->companyA->id,
            'type' => 'speeding',
            'message' => 'Pending',
            'acknowledged' => false,
        ]);

        $overview = app(AggregateFleetOverviewAction::class)->execute((string) $this->companyA->id);

        $this->assertSame(
            [
                'total_vehicles' => 3,
                'active' => 1,
                'in_maintenance' => 1,
                'decommissioned' => 1,
                'unacknowledged_alerts' => 1,
            ],
            $overview
        );
    }

    public function test_live_map_calls_traccar_once_with_tracked_active_vehicles_only(): void
    {
        $tracked = $this->vehicle($this->companyA, [
            'plate_number' => 'DZ-TRACK',
            'status' => 'active',
            'traccar_device_id' => 42,
        ]);
        $this->vehicle($this->companyA, ['status' => 'active', 'traccar_device_id' => null]);
        $this->vehicle($this->companyA, ['status' => 'maintenance', 'traccar_device_id' => 77]);
        $this->vehicle($this->companyB, ['status' => 'active', 'traccar_device_id' => 99]);

        $this->fakeTraccar([
            42 => ['latitude' => 36.7538, 'longitude' => 3.0588, 'speed' => 32],
            77 => ['latitude' => 1.0, 'longitude' => 2.0],
            99 => ['latitude' => 3.0, 'longitude' => 4.0],
        ]);

        $positions = app(BuildFleetLiveMapAction::class)->execute((string) $this->companyA->id);

        $this->assertCount(1, $positions);
        $this->assertSame((int) $tracked->id, $positions[0]['vehicle_id']);
        $this->assertSame('DZ-TRACK', (string) $positions[0]['plate_number']);
        $this->assertSame(36.7538, $positions[0]['position']['latitude']);
    }

    public function test_live_map_ignores_vehicles_without_tracker(): void
    {
        $this->vehicle($this->companyA, ['status' => 'active', 'traccar_device_id' => null]);
        $this->fakeTraccar([]);

        $positions = app(BuildFleetLiveMapAction::class)->execute((string) $this->companyA->id);

        $this->assertSame([], $positions);
    }

    // ── Rapports ────────────────────────────────────────────────────────

    public function test_fuel_report_groups_by_vehicle_inside_period(): void
    {
        $vehicle = $this->vehicle($this->companyA, ['plate_number' => 'DZ-FUEL']);

        VehicleTrip::query()->create([
            'company_id' => (string) $this->companyA->id,
            'vehicle_id' => $vehicle->id,
            'start_time' => '2026-05-05 08:00:00',
            'distance_km' => 120.5,
            'avg_speed_kmh' => 60,
            'fuel_consumed' => 12.3,
        ]);
        VehicleTrip::query()->create([
            'company_id' => (string) $this->companyA->id,
            'vehicle_id' => $vehicle->id,
            'start_time' => '2026-05-06 08:00:00',
            'distance_km' => 80.5,
            'avg_speed_kmh' => 50,
            'fuel_consumed' => 7.7,
        ]);
        VehicleTrip::query()->create([
            'company_id' => (string) $this->companyA->id,
            'vehicle_id' => $vehicle->id,
            'start_time' => '2026-05-06 08:00:00',
            'distance_km' => 10.0,
            'fuel_consumed' => null,
        ]);

        $rows = app(ReportVehicleFuelConsumptionAction::class)
            ->execute((string) $this->companyA->id, '2026-05-01', '2026-05-31');

        $this->assertCount(1, $rows);
        $this->assertSame((int) $vehicle->id, (int) $rows->first()->vehicle_id);
        $this->assertEqualsWithDelta(20.0, (float) $rows->first()->total_fuel, 0.01);
        $this->assertEqualsWithDelta(201.0, (float) $rows->first()->total_distance, 0.01);
    }

    public function test_mileage_report_groups_and_averages_inside_period(): void
    {
        $vehicle = $this->vehicle($this->companyA, ['plate_number' => 'DZ-KM']);

        foreach ([['2026-05-05 08:00:00', 120.5, 60], ['2026-05-06 08:00:00', 80.5, 50]] as [$start, $km, $speed]) {
            VehicleTrip::query()->create([
                'company_id' => (string) $this->companyA->id,
                'vehicle_id' => $vehicle->id,
                'start_time' => $start,
                'distance_km' => $km,
                'avg_speed_kmh' => $speed,
                'fuel_consumed' => 5.0,
            ]);
        }

        $rows = app(ReportVehicleMileageAction::class)
            ->execute((string) $this->companyA->id, '2026-05-01', '2026-05-31');

        $this->assertCount(1, $rows);
        $this->assertSame(2, (int) $rows->first()->trip_count);
        $this->assertEqualsWithDelta(201.0, (float) $rows->first()->total_km, 0.01);
        $this->assertEqualsWithDelta(55.0, (float) $rows->first()->avg_speed, 0.01);
    }

    public function test_maintenance_due_returns_only_soonest_window_for_current_tenant(): void
    {
        $soon = $this->vehicle($this->companyA, ['plate_number' => 'DZ-SOON']);
        $later = $this->vehicle($this->companyA, ['plate_number' => 'DZ-LATER']);
        $other = $this->vehicle($this->companyB, ['plate_number' => 'FR-OTHER']);

        VehicleMaintenance::query()->create([
            'company_id' => (string) $this->companyA->id,
            'vehicle_id' => $soon->id,
            'type' => 'oil_change',
            'service_date' => now()->subMonths(3)->toDateString(),
            'next_service_date' => now()->addDays(10)->toDateString(),
        ]);
        VehicleMaintenance::query()->create([
            'company_id' => (string) $this->companyA->id,
            'vehicle_id' => $later->id,
            'type' => 'inspection',
            'service_date' => now()->subMonths(3)->toDateString(),
            'next_service_date' => now()->addDays(45)->toDateString(),
        ]);
        VehicleMaintenance::query()->create([
            'company_id' => (string) $this->companyB->id,
            'vehicle_id' => $other->id,
            'type' => 'oil_change',
            'service_date' => now()->subMonths(3)->toDateString(),
            'next_service_date' => now()->addDays(5)->toDateString(),
        ]);

        $due = app(ListVehiclesDueForMaintenanceAction::class)->execute((string) $this->companyA->id);

        $this->assertCount(1, $due);
        $this->assertSame((int) $soon->id, (int) $due->first()->vehicle_id);
        $this->assertSame('DZ-SOON', (string) $due->first()->vehicle?->plate_number);
    }

    public function test_maintenance_due_honours_custom_window(): void
    {
        $later = $this->vehicle($this->companyA, ['plate_number' => 'DZ-LATER']);

        VehicleMaintenance::query()->create([
            'company_id' => (string) $this->companyA->id,
            'vehicle_id' => $later->id,
            'type' => 'inspection',
            'service_date' => now()->subMonths(3)->toDateString(),
            'next_service_date' => now()->addDays(45)->toDateString(),
        ]);

        $due = app(ListVehiclesDueForMaintenanceAction::class)->execute((string) $this->companyA->id, 60);

        $this->assertCount(1, $due);
    }

    // ── Maintenance ─────────────────────────────────────────────────────

    public function test_record_update_and_delete_maintenance_actions(): void
    {
        $vehicle = $this->vehicle($this->companyA);

        $record = app(RecordVehicleMaintenanceAction::class)->execute(
            (string) $this->companyA->id,
            [
                'vehicle_id' => (int) $vehicle->id,
                'type' => 'oil_change',
                'description' => 'Vidange 20 000 km',
                'cost' => 15000,
                'currency' => 'DZD',
                'mileage_at_service' => 20000,
                'service_date' => '2026-09-01',
                'next_service_date' => '2027-03-01',
                'provider' => 'Garage Alger Centre',
            ],
        );

        $this->assertSame((string) $this->companyA->id, (string) $record->company_id);
        $this->assertSame('Garage Alger Centre', $record->provider);

        $updated = app(UpdateVehicleMaintenanceAction::class)->execute($record, [
            'provider' => 'Garage Bab Ezzouar',
            'next_service_mileage' => 40000,
        ]);

        $this->assertSame('Garage Bab Ezzouar', $updated->provider);
        $this->assertSame('oil_change', $updated->type);

        app(DeleteVehicleMaintenanceAction::class)->execute($updated);

        $this->assertDatabaseMissing('vehicle_maintenances', ['id' => $record->id]);
    }
}
