<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCity;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Domain\Models\TravelRentalVehicle;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelStation;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelVehicle;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-322 (#6052) — Matrice RBAC globale de la verticale TravelAgency.
 *
 * Chaque surface d'écriture est testée avec un rôle refusé (employé simple
 * → 403) et la lecture avec un rôle autorisé (200). Les payloads sont
 * VALIDES : la validation (422) précède la Policy dans la convention du
 * module (FormRequest.authorize() = true, contrôle dans le contrôleur) —
 * un payload valide garantit qu'on atteint bien la branche 403.
 */
class TravelRbacMatrixTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function actingAsRole(Company $company, string $role, ?string $managerRole = null): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        Sanctum::actingAs($employee);
    }

    /**
     * Crée les références tenant minimales pour des payloads valides.
     *
     * @return array{city: int, route: int, trip: int, class: int}
     */
    private function references(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $city = TravelCity::factory()->create();
            $other = TravelCity::factory()->create();
            $route = TravelRoute::factory()->create([
                'origin_city_id' => $city->id,
                'destination_city_id' => $other->id,
            ]);
            $trip = TravelTrip::factory()->create(['route_id' => $route->id, 'status' => 'published']);
            $class = TravelClass::factory()->create();

            return [
                'city' => $city->id,
                'route' => $route->id,
                'trip' => $trip->id,
                'class' => $class->id,
            ];
        });
    }

    public function test_plain_employee_cannot_write_reference_data(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingAsRole($company, 'employee');

        $refs = $this->references($company);

        $this->postJson('/api/v1/travel/stations', ['code' => 'STA-X', 'name' => 'Gare X', 'city_id' => $refs['city']])->assertStatus(403);
        $this->postJson('/api/v1/travel/offices', ['name' => 'Bureau X', 'city_id' => $refs['city']])->assertStatus(403);
        $this->postJson('/api/v1/travel/carriers', ['code' => 'CAR-X', 'name' => 'Compagnie X'])->assertStatus(403);
        $this->postJson('/api/v1/travel/classes', ['code' => 'CLS-X', 'label' => 'Classe X'])->assertStatus(403);
        $this->postJson('/api/v1/travel/vehicles', ['code' => 'VEH-X', 'seat_capacity' => 40])->assertStatus(403);
        $this->postJson('/api/v1/travel/routes', [
            'code' => 'RTE-X',
            'origin_city_id' => $refs['city'],
            'destination_city_id' => $this->secondCity($company),
        ])->assertStatus(403);
        $this->postJson('/api/v1/travel/trips', [
            'code' => 'TRP-X',
            'route_id' => $refs['route'],
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'arrival_date' => now()->addDays(3)->toDateString(),
            'arrival_time' => '12:00',
            'total_seats' => 40,
        ])->assertStatus(403);
    }

    private function secondCity(Company $company): int
    {
        return app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelCity::factory()->create()->id;
        });
    }

    public function test_plain_employee_can_read_reference_data(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingAsRole($company, 'employee');

        $this->getJson('/api/v1/travel/stations')->assertOk();
        $this->getJson('/api/v1/travel/offices')->assertOk();
        $this->getJson('/api/v1/travel/carriers')->assertOk();
        $this->getJson('/api/v1/travel/classes')->assertOk();
        $this->getJson('/api/v1/travel/vehicles')->assertOk();
        $this->getJson('/api/v1/travel/routes')->assertOk();
        $this->getJson('/api/v1/travel/trips')->assertOk();
        $this->getJson('/api/v1/travel/bookings')->assertOk();
        $this->getJson('/api/v1/travel/rental-vehicles')->assertOk();
        $this->getJson('/api/v1/travel/rental-bookings')->assertOk();
        $this->getJson('/api/v1/travel/hotels')->assertOk();
        $this->getJson('/api/v1/travel/trips/search')->assertOk();
    }

    public function test_plain_employee_cannot_create_booking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingAsRole($company, 'employee');

        $refs = $this->references($company);

        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $refs['trip'],
            'booking_source' => 'office',
            'idempotency_key' => 'rbac-001',
            'passengers' => [['full_name' => 'Jean', 'age_category' => 'adult', 'class_id' => $refs['class']]],
        ])->assertStatus(403);
    }

    public function test_manager_can_create_booking_flow_resources(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingAsRole($company, 'manager', 'principal');

        $refs = $this->references($company);

        $this->postJson('/api/v1/travel/stations', ['code' => 'STA-1', 'name' => 'Gare A', 'city_id' => $refs['city']])
            ->assertStatus(201);

        $this->postJson('/api/v1/travel/classes', ['code' => 'ECO', 'label' => 'Économique'])->assertStatus(201);

        $this->postJson('/api/v1/travel/vehicles', ['code' => 'VEH-1', 'seat_capacity' => 40])->assertStatus(201);

        $this->postJson('/api/v1/travel/hotels', ['name' => 'Hôtel A', 'city_id' => $refs['city'], 'classification' => 4])->assertStatus(201);
    }

    public function test_plain_employee_cannot_manage_rentals_or_hotels(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->actingAsRole($company, 'employee');

        $cityId = $this->secondCity($company);

        $this->postJson('/api/v1/travel/rental-vehicles', [
            'code' => 'R-1',
            'title' => 'Véhicule X',
            'city_id' => $cityId,
            'price_per_day_minor' => 1000,
            'currency' => 'XAF',
        ])->assertStatus(403);

        $vehicleId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRentalVehicle::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/rental-bookings', [
            'vehicle_id' => $vehicleId,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'idempotency_key' => 'rbac-002',
        ])->assertStatus(403);

        $this->postJson('/api/v1/travel/hotels', ['name' => 'Hôtel X', 'city_id' => $cityId, 'classification' => 4])->assertStatus(403);
    }

    public function test_feature_flag_absent_returns_403(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        // Pas d'activation du flag travelagency.
        $this->actingAsRole($company, 'manager', 'principal');

        $this->getJson('/api/v1/travel/stations')->assertStatus(403);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/travel/stations')->assertStatus(401);
    }

    public function test_manage_action_on_other_tenant_resource_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->actingAsRole($companyA, 'manager', 'principal');

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $resourceIds = app(TenantManager::class)->withinTenant($companyB, function (): array {
            return [
                'station' => TravelStation::factory()->create()->id,
                'carrier' => TravelCarrier::factory()->create()->id,
                'route' => TravelRoute::factory()->create()->id,
                'trip' => TravelTrip::factory()->create()->id,
                'vehicle' => TravelVehicle::factory()->create()->id,
                'office' => TravelOffice::factory()->create()->id,
                'class' => TravelClass::factory()->create()->id,
            ];
        });

        $this->deleteJson("/api/v1/travel/stations/{$resourceIds['station']}")->assertStatus(404);
        $this->deleteJson("/api/v1/travel/carriers/{$resourceIds['carrier']}")->assertStatus(404);
        $this->deleteJson("/api/v1/travel/routes/{$resourceIds['route']}")->assertStatus(404);
        $this->deleteJson("/api/v1/travel/trips/{$resourceIds['trip']}")->assertStatus(404);
        $this->deleteJson("/api/v1/travel/vehicles/{$resourceIds['vehicle']}")->assertStatus(404);
        $this->deleteJson("/api/v1/travel/offices/{$resourceIds['office']}")->assertStatus(404);
        $this->deleteJson("/api/v1/travel/classes/{$resourceIds['class']}")->assertStatus(404);
    }
}
