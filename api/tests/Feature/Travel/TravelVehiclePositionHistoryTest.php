<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\TravelVehiclePositionSource;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelVehiclePositionLog;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8548 (TRAVEL-OS) — ingestion d'une position transmise par un dispositif
 * autorisé, et HISTORIQUE des positions réellement reçues.
 *
 * Ce que ces tests prouvent :
 *  - une transmission alimente l'instantané ET l'historique, dans la même
 *    transaction (jamais l'un sans l'autre) ;
 *  - l'horodatage de la mesure transmis par le dispositif est conservé ;
 *  - une date future ou trop ancienne est REFUSÉE (une mesure ne peut pas
 *    avoir eu lieu plus tard que maintenant) ;
 *  - l'historique est borné par tenant, par véhicule et par fenêtre ;
 *  - un véhicule sans dispositif retourne une liste VIDE — jamais des points
 *    fabriqués ;
 *  - l'isolation par entreprise est respectée (404 cross-tenant).
 */
class TravelVehiclePositionHistoryTest extends TestCase
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

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($company);

        return $company;
    }

    // ---------------------------------------------------------------------
    // Ingestion — instantané + historique, atomiques
    // ---------------------------------------------------------------------

    public function test_transmission_records_snapshot_and_history_together(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-1'])->id
        );

        $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
            'latitude' => 14.6928,
            'longitude' => -17.4467,
            'source' => 'gps',
        ])->assertOk()
            ->assertJsonPath('data.position.source', 'gps')
            ->assertJsonPath('data.position.is_measured', true);

        // L'instantané est à jour…
        $this->assertDatabaseHas('travel_vehicles', [
            'id' => $vehicleId,
            'position_source' => 'gps',
        ]);

        // …ET l'historique contient exactement une ligne (même transaction).
        $this->assertDatabaseCount('travel_vehicle_position_logs', 1);
        $this->assertDatabaseHas('travel_vehicle_position_logs', [
            'vehicle_id' => $vehicleId,
            'source' => 'gps',
        ]);
    }

    public function test_device_timestamp_is_preserved(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-2'])->id
        );

        $measuredAt = now()->subMinutes(12)->toIso8601String();

        $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
            'latitude' => 14.5,
            'longitude' => -17.0,
            'source' => 'app',
            'recorded_at' => $measuredAt,
        ])->assertOk();

        $log = TravelVehiclePositionLog::query()->firstOrFail();

        // L'horodatage de la MESURE est conservé, pas celui de la réception.
        $this->assertSame(
            now()->subMinutes(12)->toDateTimeString(),
            $log->recorded_at->toDateTimeString()
        );
    }

    public function test_future_measurement_timestamp_is_rejected(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-3'])->id
        );

        // Une mesure ne peut pas avoir eu lieu plus tard que maintenant.
        $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
            'latitude' => 14.5,
            'longitude' => -17.0,
            'source' => 'gps',
            'recorded_at' => now()->addHours(2)->toIso8601String(),
        ])->assertStatus(422);

        $this->assertDatabaseCount('travel_vehicle_position_logs', 0);
    }

    public function test_too_old_measurement_timestamp_is_rejected(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-4'])->id
        );

        $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
            'latitude' => 14.5,
            'longitude' => -17.0,
            'source' => 'gps',
            'recorded_at' => now()->subDays(60)->toIso8601String(),
        ])->assertStatus(422);

        $this->assertDatabaseCount('travel_vehicle_position_logs', 0);
    }

    public function test_unknown_source_is_rejected(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-5'])->id
        );

        // Le serveur ne devine jamais d'où vient une position.
        $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
            'latitude' => 14.5,
            'longitude' => -17.0,
            'source' => 'satellite',
        ])->assertStatus(422);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-6'])->id
        );

        $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
            'latitude' => 120.0,
            'longitude' => -17.0,
            'source' => 'gps',
        ])->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // Historique — lecture bornée et honnête
    // ---------------------------------------------------------------------

    public function test_history_is_empty_without_device(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-NODEV'])->id
        );

        // Aucun dispositif n'a transmis : liste VIDE, jamais des points
        // fabriqués.
        $this->getJson("/api/v1/travel/vehicles/{$vehicleId}/positions")
            ->assertOk()
            ->assertJsonPath('data.count', 0)
            ->assertJsonCount(0, 'data.points');
    }

    public function test_history_returns_transmitted_points_most_recent_first(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-7'])->id
        );

        foreach ([30, 20, 10] as $minutesAgo) {
            $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
                'latitude' => 14.0 + $minutesAgo / 1000,
                'longitude' => -17.0,
                'source' => 'gps',
                'recorded_at' => now()->subMinutes($minutesAgo)->toIso8601String(),
            ])->assertOk();
        }

        $response = $this->getJson("/api/v1/travel/vehicles/{$vehicleId}/positions");

        $response->assertOk()
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.points.0.source', 'gps')
            ->assertJsonPath('data.points.0.is_measured', true);

        // Le plus récent d'abord (10 min < 20 min < 30 min).
        $points = $response->json('data.points');
        $this->assertTrue($points[0]['recorded_at'] > $points[1]['recorded_at']);
        $this->assertTrue($points[1]['recorded_at'] > $points[2]['recorded_at']);
    }

    public function test_history_can_be_bounded_by_time_window(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-8'])->id
        );

        foreach ([120, 60, 5] as $minutesAgo) {
            $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
                'latitude' => 14.0,
                'longitude' => -17.0,
                'source' => 'gps',
                'recorded_at' => now()->subMinutes($minutesAgo)->toIso8601String(),
            ])->assertOk();
        }

        // Fenêtre : uniquement les 30 dernières minutes → 1 point.
        $this->getJson(
            "/api/v1/travel/vehicles/{$vehicleId}/positions?from="
            .urlencode(now()->subMinutes(30)->toIso8601String())
        )->assertOk()->assertJsonPath('data.count', 1);
    }

    public function test_history_limit_is_capped(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-9'])->id
        );

        // `limit` est plafonné à 500 : une valeur abusive est refusée.
        $this->getJson("/api/v1/travel/vehicles/{$vehicleId}/positions?limit=5000")
            ->assertStatus(422);
    }

    public function test_estimated_point_is_never_measured(): void
    {
        $company = $this->company();
        $this->principal($company);

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-EST'])->id
        );

        $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
            'latitude' => 14.0,
            'longitude' => -17.0,
            'source' => 'estimated',
        ])->assertOk();

        // Une estimation n'est JAMAIS présentée comme une mesure GPS.
        $this->getJson("/api/v1/travel/vehicles/{$vehicleId}/positions")
            ->assertOk()
            ->assertJsonPath('data.points.0.source', 'estimated')
            ->assertJsonPath('data.points.0.is_measured', false);
    }

    // ---------------------------------------------------------------------
    // Sécurité / isolation
    // ---------------------------------------------------------------------

    public function test_history_of_another_tenant_returns_404(): void
    {
        $companyA = $this->company();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyB);

        $foreignId = app(TenantManager::class)->withinTenant(
            $companyB,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-FOREIGN'])->id
        );

        $this->principal($companyA);

        // 404 (jamais 403) : ne rien divulguer sur l'existence du véhicule.
        $this->getJson("/api/v1/travel/vehicles/{$foreignId}/positions")
            ->assertStatus(404);
    }

    public function test_transmission_to_another_tenant_returns_404(): void
    {
        $companyA = $this->company();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->activateTravel($companyB);

        $foreignId = app(TenantManager::class)->withinTenant(
            $companyB,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-FOREIGN-2'])->id
        );

        $this->principal($companyA);

        $this->postJson("/api/v1/travel/vehicles/{$foreignId}/position", [
            'latitude' => 14.0,
            'longitude' => -17.0,
            'source' => 'gps',
        ])->assertStatus(404);

        $this->assertDatabaseCount('travel_vehicle_position_logs', 0);
    }

    public function test_simple_employee_cannot_transmit_a_position(): void
    {
        $company = $this->company();

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-10'])->id
        );

        $this->simpleEmployee($company);

        // L'ingestion exige `travel.manage` (principal/rh) : un dispositif
        // autorisé agit via un compte habilité.
        $this->postJson("/api/v1/travel/vehicles/{$vehicleId}/position", [
            'latitude' => 14.0,
            'longitude' => -17.0,
            'source' => 'gps',
        ])->assertStatus(403);
    }

    public function test_simple_employee_can_read_history(): void
    {
        $company = $this->company();

        $vehicleId = app(TenantManager::class)->withinTenant(
            $company,
            fn (): int => TravelVehicle::factory()->create(['code' => 'BUS-11'])->id
        );

        $this->simpleEmployee($company);

        // Lecture ouverte à tout employé du tenant (TravelVehiclePolicy::view).
        $this->getJson("/api/v1/travel/vehicles/{$vehicleId}/positions")->assertOk();
    }

    public function test_history_is_scoped_to_the_vehicle(): void
    {
        $company = $this->company();
        $this->principal($company);

        [$firstId, $secondId] = app(TenantManager::class)->withinTenant(
            $company,
            fn (): array => [
                TravelVehicle::factory()->create(['code' => 'BUS-A'])->id,
                TravelVehicle::factory()->create(['code' => 'BUS-B'])->id,
            ]
        );

        $this->postJson("/api/v1/travel/vehicles/{$firstId}/position", [
            'latitude' => 14.0,
            'longitude' => -17.0,
            'source' => 'gps',
        ])->assertOk();

        // Le second véhicule n'a rien transmis : son historique reste vide.
        $this->getJson("/api/v1/travel/vehicles/{$secondId}/positions")
            ->assertOk()
            ->assertJsonPath('data.count', 0);

        $this->getJson("/api/v1/travel/vehicles/{$firstId}/positions")
            ->assertOk()
            ->assertJsonPath('data.count', 1);
    }

    public function test_estimated_source_enum_is_never_measured(): void
    {
        $this->assertFalse(TravelVehiclePositionSource::ESTIMATED->isMeasured());
        $this->assertTrue(TravelVehiclePositionSource::GPS->isMeasured());
        $this->assertTrue(TravelVehiclePositionSource::APP->isMeasured());
    }
}
