<?php

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Fleet\Domain\Models\VehicleAlert;
use App\Modules\Fleet\Domain\Models\VehicleMaintenance;
use App\Modules\Fleet\Domain\Models\VehicleTrip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9542 — contrat observable de `GET /api/v1/fleet/stats`.
 *
 * L'endpoint etait documente dans `openapi.yaml` mais n'etait couvert par
 * aucun test, et il interrogeait trois colonnes qui n'existent pas dans le
 * schema (`is_resolved`, `performed_at`, `registration_number`) : il
 * repondait donc 500 en production. Ces tests mesurent la reponse HTTP, ce
 * qui rend le defaut impossible a reintroduire sans rouge.
 */
final class FleetStatsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function vehicle(Company $company, array $overrides = []): Vehicle
    {
        /** @var Vehicle $vehicle */
        $vehicle = Vehicle::query()->create(array_merge([
            'company_id' => $company->id,
            'plate_number' => 'DZ-'.uniqid('', false),
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2024,
            'type' => 'van',
            'fuel_type' => 'diesel',
            'status' => 'active',
        ], $overrides));

        return $vehicle;
    }

    private function trip(Vehicle $vehicle, float $km, string $start = '2026-04-10 08:00:00'): void
    {
        VehicleTrip::query()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $vehicle->company_id,
            'start_time' => $start,
            'end_time' => $start,
            'distance_km' => $km,
        ]);
    }

    public function test_a_manager_gets_the_consolidated_statistics(): void
    {
        $active = $this->vehicle($this->company, ['plate_number' => 'DZ-0001-AA']);
        $idle = $this->vehicle($this->company, ['plate_number' => 'DZ-0002-BB', 'status' => 'maintenance']);
        $this->vehicle($this->company, ['plate_number' => 'DZ-0003-CC', 'status' => 'decommissioned']);

        $this->trip($active, 100.0);
        $this->trip($idle, 50.0);

        VehicleMaintenance::query()->create([
            'vehicle_id' => $active->id,
            'company_id' => $this->company->id,
            'type' => 'oil_change',
            'description' => 'Vidange',
            'cost' => 20000,
            'service_date' => '2026-04-12',
        ]);

        VehicleAlert::query()->create([
            'vehicle_id' => $active->id,
            'company_id' => $this->company->id,
            'type' => 'speeding',
            'message' => 'Exces de vitesse',
            'acknowledged' => false,
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/fleet/stats?from=2026-04-01&to=2026-04-30');

        $response->assertOk();
        $response->assertJsonPath('data.period.from', '2026-04-01');
        $response->assertJsonPath('data.period.to', '2026-04-30');

        $response->assertJsonPath('data.fleet_overview.total_vehicles', 3);
        $response->assertJsonPath('data.fleet_overview.active', 1);
        $response->assertJsonPath('data.fleet_overview.in_maintenance', 1);
        $response->assertJsonPath('data.fleet_overview.decommissioned', 1);

        self::assertSame(150.0, (float) $response->json('data.mileage.total_km'));
        $response->assertJsonPath('data.mileage.total_trips', 2);

        self::assertSame(20000.0, (float) $response->json('data.maintenance.total_cost'));
        $response->assertJsonPath('data.maintenance.count', 1);

        $response->assertJsonPath('data.alerts.active_count', 1);

        $top = $response->json('data.top_vehicles');
        self::assertIsArray($top);
        self::assertNotEmpty($top);
        self::assertSame($active->id, $top[0]['vehicle_id']);
        // La plaque vient de `plate_number` : une colonne inventee rendait
        // toujours « Unknown » sans faire echouer l'appel.
        self::assertSame('DZ-0001-AA', $top[0]['registration']);
    }

    public function test_acknowledged_alerts_are_not_counted_as_active(): void
    {
        $vehicle = $this->vehicle($this->company);

        VehicleAlert::query()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'type' => 'idle',
            'message' => 'Arret prolonge',
            'acknowledged' => true,
        ]);

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/fleet/stats?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertJsonPath('data.alerts.active_count', 0);
    }

    public function test_maintenance_outside_the_period_is_ignored(): void
    {
        $vehicle = $this->vehicle($this->company);

        VehicleMaintenance::query()->create([
            'vehicle_id' => $vehicle->id,
            'company_id' => $this->company->id,
            'type' => 'repair',
            'cost' => 5000,
            'service_date' => '2026-05-05',
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/fleet/stats?from=2026-04-01&to=2026-04-30');

        $response->assertOk();
        $response->assertJsonPath('data.maintenance.count', 0);
        self::assertSame(0.0, (float) $response->json('data.maintenance.total_cost'));
    }

    public function test_the_vehicle_filter_narrows_the_statistics(): void
    {
        $first = $this->vehicle($this->company);
        $second = $this->vehicle($this->company);

        $this->trip($first, 100.0);
        $this->trip($second, 50.0);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson(
            '/api/v1/fleet/stats?from=2026-04-01&to=2026-04-30&vehicle_id='.$first->id
        );

        $response->assertOk();
        $response->assertJsonPath('data.fleet_overview.total_vehicles', 1);
        self::assertSame(100.0, (float) $response->json('data.mileage.total_km'));
    }

    /**
     * Un vehicule d'une AUTRE societe est refuse comme un identifiant
     * inexistant : distinguer les deux reponses confirmerait l'existence de la
     * ligne et permettrait d'enumerer le parc client.
     */
    public function test_a_vehicle_id_from_another_tenant_is_rejected(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create();
        $foreign = $this->vehicle($other);

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/fleet/stats?vehicle_id='.$foreign->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_an_unknown_vehicle_id_is_rejected_the_same_way(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/fleet/stats?vehicle_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_a_non_manager_cannot_read_the_statistics(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/fleet/stats')->assertForbidden();
    }

    public function test_an_unauthenticated_caller_gets_401(): void
    {
        $this->getJson('/api/v1/fleet/stats')->assertUnauthorized();
    }
}
