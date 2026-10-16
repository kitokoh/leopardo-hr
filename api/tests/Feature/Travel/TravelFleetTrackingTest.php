<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\BuildTravelFleetTrackingAction;
use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\ValueObjects\VehiclePositionFreshness;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8548 (TRAVEL-OS) — tableau de bord de suivi de la flotte.
 *
 * Vérifie que la lecture est HONNÊTE : une position absente est
 * `unavailable` (jamais « à l'heure »), une position ancienne est `stale`
 * (jamais « courante »), une position `estimated` n'est jamais exposée comme
 * une mesure GPS, et l'isolation par entreprise est respectée.
 */
class TravelFleetTrackingTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function principal(Company $company): Employee
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

    private function simpleEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    // ---------------------------------------------------------------------
    // Value object — fraîcheur opposable
    // ---------------------------------------------------------------------

    public function test_freshness_is_unavailable_without_recorded_at(): void
    {
        $freshness = VehiclePositionFreshness::fromNullable(null, 30);

        $this->assertTrue($freshness->isUnavailable());
        $this->assertFalse($freshness->isCurrent());
        $this->assertFalse($freshness->isStale());
        $this->assertSame('Position indisponible', $freshness->label());
        $this->assertNull($freshness->ageSeconds);
    }

    public function test_freshness_is_fresh_within_threshold(): void
    {
        $freshness = VehiclePositionFreshness::fromRecordedAt(now()->subMinutes(5), 30);

        $this->assertTrue($freshness->isCurrent());
        $this->assertFalse($freshness->isStale());
        $this->assertSame('Position récente', $freshness->label());
    }

    public function test_freshness_is_stale_beyond_threshold(): void
    {
        $freshness = VehiclePositionFreshness::fromRecordedAt(now()->subMinutes(90), 30);

        $this->assertTrue($freshness->isStale());
        $this->assertFalse($freshness->isCurrent());
        $this->assertSame('Position ancienne', $freshness->label());
    }

    public function test_estimated_source_is_never_measured(): void
    {
        $this->assertFalse(VehiclePositionFreshness::isMeasuredSource(TravelVehiclePositionSource::ESTIMATED));
        $this->assertTrue(VehiclePositionFreshness::isMeasuredSource(TravelVehiclePositionSource::GPS));
        $this->assertTrue(VehiclePositionFreshness::isMeasuredSource(TravelVehiclePositionSource::APP));
        $this->assertFalse(VehiclePositionFreshness::isMeasuredSource(null));
    }

    // ---------------------------------------------------------------------
    // Tableau de bord — lecture honnête
    // ---------------------------------------------------------------------

    public function test_vehicle_without_device_has_no_position_and_is_unavailable(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelVehicle::factory()->create(['code' => 'BUS-NOGPS']);
        });

        $response = $this->getJson('/api/v1/travel/fleet/tracking');

        $response->assertOk()
            ->assertJsonPath('data.vehicles.0.position', null)
            ->assertJsonPath('data.vehicles.0.freshness.state', 'unavailable')
            ->assertJsonPath('data.vehicles.0.freshness.is_current', false)
            ->assertJsonPath('data.summary.unavailable', 1)
            ->assertJsonPath('data.summary.with_position', 0);
    }

    public function test_stale_position_is_flagged_and_never_current(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelVehicle::factory()->create([
                'code' => 'BUS-OLD',
                'latitude' => 14.6928,
                'longitude' => -17.4467,
                'position_source' => TravelVehiclePositionSource::GPS->value,
                'position_recorded_at' => now()->subHours(3),
            ]);
        });

        $response = $this->getJson('/api/v1/travel/fleet/tracking');

        $response->assertOk()
            ->assertJsonPath('data.vehicles.0.freshness.state', 'stale')
            ->assertJsonPath('data.vehicles.0.freshness.is_current', false)
            ->assertJsonPath('data.vehicles.0.freshness.is_stale', true)
            ->assertJsonPath('data.vehicles.0.position.is_measured', true)
            ->assertJsonPath('data.summary.stale', 1)
            ->assertJsonPath('data.summary.current', 0);
    }

    public function test_fresh_measured_position_is_current(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelVehicle::factory()->create([
                'code' => 'BUS-NEW',
                'latitude' => 14.6928,
                'longitude' => -17.4467,
                'position_source' => TravelVehiclePositionSource::APP->value,
                'position_recorded_at' => now()->subMinutes(2),
            ]);
        });

        $response = $this->getJson('/api/v1/travel/fleet/tracking');

        $response->assertOk()
            ->assertJsonPath('data.vehicles.0.freshness.state', 'fresh')
            ->assertJsonPath('data.vehicles.0.freshness.is_current', true)
            ->assertJsonPath('data.vehicles.0.position.source', 'app')
            ->assertJsonPath('data.vehicles.0.position.is_measured', true)
            ->assertJsonPath('data.summary.current', 1);
    }

    public function test_estimated_position_is_never_exposed_as_measured(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelVehicle::factory()->create([
                'code' => 'BUS-EST',
                'latitude' => 14.6928,
                'longitude' => -17.4467,
                'position_source' => TravelVehiclePositionSource::ESTIMATED->value,
                'position_recorded_at' => now()->subMinutes(1),
            ]);
        });

        $response = $this->getJson('/api/v1/travel/fleet/tracking');

        $response->assertOk()
            ->assertJsonPath('data.vehicles.0.position.is_measured', false)
            ->assertJsonPath('data.vehicles.0.position.source', 'estimated')
            ->assertJsonPath('data.summary.estimated', 1)
            ->assertJsonPath('data.summary.measured', 0);
    }

    public function test_dashboard_lists_upcoming_departures_of_the_vehicle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $vehicle = TravelVehicle::factory()->create(['code' => 'BUS-DEP']);

            TravelTrip::factory()->create([
                'vehicle_id' => $vehicle->id,
                'status' => 'published',
                'departure_date' => now()->addDays(2)->toDateString(),
                'departure_time' => '08:30',
            ]);

            // Trajet passé : ne doit PAS apparaître dans les départs à venir.
            TravelTrip::factory()->create([
                'vehicle_id' => $vehicle->id,
                'status' => 'published',
                'departure_date' => now()->subDays(3)->toDateString(),
                'departure_time' => '07:00',
            ]);
        });

        $response = $this->getJson('/api/v1/travel/fleet/tracking');

        $response->assertOk()
            ->assertJsonCount(1, 'data.vehicles.0.upcoming_departures')
            ->assertJsonPath('data.vehicles.0.upcoming_departures.0.departure_time', '08:30:00')
            ->assertJsonPath('data.vehicles.0.upcoming_departures.0.status', 'published');
    }

    public function test_dashboard_can_target_a_single_vehicle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->principal($company);

        $targetId = app(TenantManager::class)->withinTenant($company, function (): int {
            TravelVehicle::factory()->create(['code' => 'BUS-A']);
            $target = TravelVehicle::factory()->create(['code' => 'BUS-B']);

            return $target->id;
        });

        $response = $this->getJson('/api/v1/travel/fleet/tracking?vehicle_id='.$targetId);

        $response->assertOk()
            ->assertJsonCount(1, 'data.vehicles')
            ->assertJsonPath('data.vehicles.0.code', 'BUS-B');
    }

    // ---------------------------------------------------------------------
    // Sécurité / isolation
    // ---------------------------------------------------------------------

    public function test_unknown_vehicle_id_is_not_found(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->getJson('/api/v1/travel/fleet/tracking?vehicle_id=999999')
            ->assertStatus(404);
    }

    public function test_vehicle_of_another_company_is_not_visible(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyB);

        $foreignId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelVehicle::factory()->create(['code' => 'BUS-FOREIGN'])->id;
        });

        $this->principal($companyA);

        // Le véhicule de l'autre entreprise est introuvable (404 sûr).
        $this->getJson('/api/v1/travel/fleet/tracking?vehicle_id='.$foreignId)
            ->assertStatus(404);

        // Et il n'apparaît pas dans la liste consolidée du tenant A.
        $this->getJson('/api/v1/travel/fleet/tracking')
            ->assertOk()
            ->assertJsonCount(0, 'data.vehicles');
    }

    public function test_simple_employee_can_read_the_dashboard(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);
        $this->simpleEmployee($company);

        // `viewAny` est ouvert à tout employé du tenant (TravelVehiclePolicy).
        $this->getJson('/api/v1/travel/fleet/tracking')->assertOk();
    }

    public function test_action_never_invents_coordinates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);

        $result = app(TenantManager::class)->withinTenant(
            $company,
            function (): array {
                TravelVehicle::factory()->create(['code' => 'BUS-EMPTY']);

                return app(BuildTravelFleetTrackingAction::class)->execute();
            }
        );

        $this->assertNull($result['vehicles'][0]['position']);
        $this->assertSame('unavailable', $result['vehicles'][0]['freshness']['state']);
    }
}
