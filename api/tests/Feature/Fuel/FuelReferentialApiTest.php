<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API référentiel FuelStation — FUEL-011 (issue #5805).
 *
 * Couvre : auth 401, RBAC (employé 403), CRUD stations/sites/pompes/cuves/
 * produits tenant-scoped, unicité par tenant, 404 sûr cross-tenant.
 */
class FuelReferentialApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(401);
        $this->getJson('/api/v1/fuel-station/products')->assertStatus(401);
    }

    public function test_operator_employee_cannot_manage_referential(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/stations', ['code' => 'ST-1', 'name' => 'S1'])->assertStatus(403);
        $this->getJson('/api/v1/fuel-station/products')->assertStatus(403);
    }

    public function test_solution_inactive_returns_403(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(403);
    }

    public function test_manager_creates_and_lists_stations(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/stations', [
            'code' => 'ST-001',
            'name' => 'Station Centrale',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'ST-001')
            ->assertJsonPath('data.name', 'Station Centrale')
            ->assertJsonPath('data.status', 'active');

        $this->getJson('/api/v1/fuel-station/stations')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'ST-001');
    }

    public function test_station_code_unique_per_company(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($managerA);
        $this->postJson('/api/v1/fuel-station/stations', ['code' => 'ST-X', 'name' => 'A'])->assertStatus(201);

        Sanctum::actingAs($managerB);
        $this->postJson('/api/v1/fuel-station/stations', ['code' => 'ST-X', 'name' => 'B'])->assertStatus(201);

        Sanctum::actingAs($managerA);
        $this->postJson('/api/v1/fuel-station/stations', ['code' => 'ST-X', 'name' => 'A2'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_cross_tenant_station_is_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        /** @var FuelStation $stationA */
        $stationA = FuelStation::query()->create([
            'company_id' => $companyA->id,
            'code' => 'ST-A',
            'name' => 'A',
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/fuel-station/stations/'.$stationA->id)->assertStatus(404);
        $this->putJson('/api/v1/fuel-station/stations/'.$stationA->id, ['name' => 'B'])->assertStatus(404);
        $this->deleteJson('/api/v1/fuel-station/stations/'.$stationA->id)->assertStatus(404);
    }

    public function test_manager_crud_sites_pumps_tanks_products(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-001',
            'name' => 'Centrale',
            'timezone' => 'UTC',
        ]);

        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/sites', ['code' => 'SITE-1', 'name' => 'Site 1'])
            ->assertStatus(201)
            ->assertJsonPath('data.station_id', $station->id);
        $this->getJson('/api/v1/fuel-station/stations/'.$station->id.'/sites')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/pumps', ['code' => 'P-1', 'product_types' => ['essence']])
            ->assertStatus(201)
            ->assertJsonPath('data.product_types', ['essence']);

        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/tanks', [
            'code' => 'T-1',
            'product_type' => 'essence',
            'capacity_minor' => 50000,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.capacity_minor', 50000);

        $this->postJson('/api/v1/fuel-station/products', ['code' => 'ESS', 'name' => 'Essence'])
            ->assertStatus(201)
            ->assertJsonPath('data.unit_code', 'l');

        $this->getJson('/api/v1/fuel-station/products')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/fuel-station/stations/'.$station->id.'/pumps')->assertStatus(200)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/fuel-station/stations/'.$station->id.'/tanks')->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_cross_tenant_nested_resources_are_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        /** @var FuelStation $stationA */
        $stationA = FuelStation::query()->create([
            'company_id' => $companyA->id,
            'code' => 'ST-A',
            'name' => 'A',
            'timezone' => 'UTC',
        ]);
        /** @var FuelSite $siteA */
        $siteA = FuelSite::query()->create([
            'company_id' => $companyA->id,
            'station_id' => $stationA->id,
            'code' => 'S-A',
            'name' => 'Site A',
        ]);
        /** @var FuelPump $pumpA */
        $pumpA = FuelPump::query()->create([
            'company_id' => $companyA->id,
            'station_id' => $stationA->id,
            'code' => 'P-A',
        ]);
        /** @var FuelTank $tankA */
        $tankA = FuelTank::query()->create([
            'company_id' => $companyA->id,
            'station_id' => $stationA->id,
            'code' => 'T-A',
            'product_type' => 'essence',
            'capacity_minor' => 1000,
        ]);
        /** @var FuelProduct $productA */
        $productA = FuelProduct::query()->create([
            'company_id' => $companyA->id,
            'code' => 'ESS',
            'name' => 'Essence',
        ]);

        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/fuel-station/stations/'.$stationA->id.'/sites')->assertStatus(404);
        $this->getJson('/api/v1/fuel-station/sites/'.$siteA->id)->assertStatus(404);
        $this->getJson('/api/v1/fuel-station/pumps/'.$pumpA->id)->assertStatus(404);
        $this->getJson('/api/v1/fuel-station/tanks/'.$tankA->id)->assertStatus(404);
        $this->getJson('/api/v1/fuel-station/products/'.$productA->id)->assertStatus(404);
    }

    public function test_validation_errors(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/stations', ['code' => '', 'name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);

        $this->postJson('/api/v1/fuel-station/stations', ['code' => 'X', 'name' => 'Y', 'status' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
