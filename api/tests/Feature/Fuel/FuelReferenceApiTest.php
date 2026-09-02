<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API & Policies manager/opérateur FuelStation — FUEL-011 (issue #5805).
 *
 * Couvre le CRUD du référentiel (stations, sites, pompes, cuves, compteurs,
 * produits) : deny-by-default (pompiste → 403), filtres allowlist, pagination
 * bornée, isolation tenant 404, routes inconnues 404.
 */
class FuelReferenceApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(401);
    }

    public function test_manager_crud_station(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/stations', [
            'code' => 'ST-001',
            'name' => 'Station Centre',
            'timezone' => 'Africa/Algiers',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'ST-001')
            ->assertJsonPath('data.name', 'Station Centre');

        $station = FuelStation::query()->firstOrFail();

        $this->getJson('/api/v1/fuel-station/stations')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $station->id);

        $this->getJson("/api/v1/fuel-station/stations/{$station->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $station->id);

        $this->putJson("/api/v1/fuel-station/stations/{$station->id}", [
            'name' => 'Station Centre Rénovée',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Station Centre Rénovée');

        $this->deleteJson("/api/v1/fuel-station/stations/{$station->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('fuel_stations', ['id' => $station->id]);
    }

    public function test_manager_crud_pumps_tanks_meters_products(): void
    {
        [$company, $manager, , $station] = $this->seedStation();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/pumps', [
            'station_id' => $station->id,
            'code' => 'P-01',
            'product_types' => ['essence', 'diesel'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'P-01');

        $pump = FuelPump::query()->firstOrFail();

        $this->postJson('/api/v1/fuel-station/tanks', [
            'station_id' => $station->id,
            'code' => 'T-01',
            'product_type' => 'essence',
            'capacity_minor' => 20000,
            'current_level_minor' => 10000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'T-01');

        $tank = FuelTank::query()->firstOrFail();

        $this->postJson('/api/v1/fuel-station/meters', [
            'station_id' => $station->id,
            'pump_id' => $pump->id,
            'meter_code' => 'M-01-A',
            'meter_type' => 'electronic',
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.meter_code', 'M-01-A');

        $meter = FuelMeterRegister::query()->firstOrFail();

        $this->postJson('/api/v1/fuel-station/products', [
            'code' => 'essence',
            'name' => 'Essence sans plomb',
            'unit_code' => 'l',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'essence');

        $product = FuelProduct::query()->firstOrFail();

        // Vérif listes + filtres.
        $this->getJson('/api/v1/fuel-station/pumps?station_id='.$station->id)
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $pump->id);

        $this->getJson('/api/v1/fuel-station/tanks?product_type=essence')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $tank->id);

        $this->getJson('/api/v1/fuel-station/meters?status=active')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $meter->id);

        $this->getJson('/api/v1/fuel-station/products?status=active')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $product->id);
    }

    public function test_operator_gets_403_on_reference_crud(): void
    {
        [$company, , $operator, $station] = $this->seedStation();

        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/stations', [
            'code' => 'ST-X',
            'name' => 'Interdit',
        ])->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/products', [
            'code' => 'gpl',
            'name' => 'GPL',
            'unit_code' => 'l',
        ])->assertStatus(403);
        $this->deleteJson('/api/v1/fuel-station/pumps/1')->assertStatus(403);
    }

    public function test_cross_tenant_reference_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $stationB = $this->createStation($companyB);

        Sanctum::actingAs($managerA);

        $this->getJson("/api/v1/fuel-station/stations/{$stationB->id}")
            ->assertStatus(404);

        $this->putJson("/api/v1/fuel-station/stations/{$stationB->id}", ['name' => 'Hack'])
            ->assertStatus(404);

        $this->deleteJson("/api/v1/fuel-station/stations/{$stationB->id}")
            ->assertStatus(404);
    }

    public function test_unknown_resource_is_404(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/vehicles')->assertStatus(404);
        $this->postJson('/api/v1/fuel-station/vehicles', [])->assertStatus(404);
    }

    public function test_pagination_is_bounded(): void
    {
        [$company, $manager] = $this->seedTenant();

        Sanctum::actingAs($manager);

        for ($i = 1; $i <= 5; $i++) {
            FuelProduct::query()->create([
                'company_id' => $company->id,
                'code' => 'PROD-'.$i,
                'name' => 'Produit '.$i,
                'unit_code' => 'l',
                'status' => FuelProduct::STATUS_ACTIVE,
            ]);
        }

        $this->getJson('/api/v1/fuel-station/products?per_page=2')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3);

        // per_page > 100 est borné à 100.
        $this->getJson('/api/v1/fuel-station/products?per_page=500')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_solution_inactive_returns_403(): void
    {
        $company = Company::factory()->create(['features' => []]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(403);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return [$company, $manager];
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee, 3: FuelStation}
     */
    private function seedStation(): array
    {
        [$company, $manager] = $this->seedTenant();
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $station = $this->createStation($company);

        return [$company, $manager, $operator, $station];
    }

    private function createStation(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }
}
