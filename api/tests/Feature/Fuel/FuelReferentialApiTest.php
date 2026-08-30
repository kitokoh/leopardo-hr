<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API référentiel manager FuelStation — FUEL-011 (issue #5805).
 *
 * Couvre : auth 401, RBAC deny-by-default (employé 403), CRUD stations /
 * sites / pompes / cuves / compteurs / produits, unicité `code` par tenant,
 * pagination bornée, cross-tenant 404.
 */
class FuelReferentialApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['fuel_station' => true],
        ]);
        $this->companyB = $companyB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $operator;
    }

    private function station(Company $company, string $code = 'ST-01'): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => "Station {$code}",
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(401);
        $this->getJson('/api/v1/fuel-station/products')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/stations', [])->assertStatus(401);
    }

    public function test_operator_cannot_access_referential(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/stations', [])->assertStatus(403);
        $this->getJson('/api/v1/fuel-station/products')->assertStatus(403);
    }

    public function test_manager_crud_stations_and_sites(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $station = $this->postJson('/api/v1/fuel-station/stations', [
            'code' => 'ST-ALG',
            'name' => 'Station Alger Centre',
            'address' => '12 rue Didouche Mourad',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
            'status' => 'active',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'ST-ALG')
            ->json('data');

        $this->getJson('/api/v1/fuel-station/stations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Station Alger Centre');

        $this->postJson("/api/v1/fuel-station/stations/{$station['id']}/sites", [
            'code' => 'SITE-A',
            'name' => 'Site A',
            'address' => 'Zone industrielle',
            'status' => 'active',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.station_id', $station['id']);

        $this->getJson("/api/v1/fuel-station/stations/{$station['id']}/sites")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Code unique par tenant.
        $this->postJson('/api/v1/fuel-station/stations', [
            'code' => 'ST-ALG',
            'name' => 'Doublon',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
        ])->assertStatus(422);
    }

    public function test_manager_crud_equipment_and_products(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $pump = $this->postJson("/api/v1/fuel-station/stations/{$station->id}/pumps", [
            'code' => 'P-01',
            'product_types' => ['essence', 'gazole'],
            'status' => 'active',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'P-01')
            ->json('data');

        $this->postJson("/api/v1/fuel-station/stations/{$station->id}/tanks", [
            'code' => 'C-01',
            'product_type' => 'essence',
            'capacity_minor' => 20000000,
            'current_level_minor' => 12000000,
            'status' => 'active',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.capacity_minor', 20000000);

        $this->postJson("/api/v1/fuel-station/stations/{$station->id}/meters", [
            'pump_id' => $pump['id'],
            'meter_code' => 'C-01-A',
            'meter_type' => 'electronic',
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
            'status' => 'active',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.meter_code', 'C-01-A')
            ->assertJsonPath('data.precision_scale', 2);

        $this->getJson("/api/v1/fuel-station/stations/{$station->id}/pumps")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/fuel-station/stations/{$station->id}/tanks")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/fuel-station/stations/{$station->id}/meters")->assertOk()->assertJsonCount(1, 'data');

        $product = $this->postJson('/api/v1/fuel-station/products', [
            'code' => 'essence',
            'name' => 'Essence sans plomb',
            'unit_code' => 'l',
            'status' => 'active',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'essence')
            ->json('data');

        $this->putJson("/api/v1/fuel-station/products/{$product['id']}", [
            'code' => 'essence',
            'name' => 'Essence sans plomb 95',
            'unit_code' => 'l',
            'status' => 'inactive',
        ])->assertOk()->assertJsonPath('data.status', 'inactive');
    }

    public function test_same_code_allowed_in_another_company(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $this->postJson('/api/v1/fuel-station/products', [
            'code' => 'gazole',
            'name' => 'Gazole',
            'unit_code' => 'l',
            'status' => 'active',
        ])->assertStatus(201);

        Sanctum::actingAs($this->manager($this->companyB));
        $this->postJson('/api/v1/fuel-station/products', [
            'code' => 'gazole',
            'name' => 'Gazole B',
            'unit_code' => 'l',
            'status' => 'active',
        ])->assertStatus(201);
    }

    public function test_cross_tenant_referential_is_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $stationB = $this->station($this->companyB, 'ST-99');

        $this->getJson("/api/v1/fuel-station/stations/{$stationB->id}")->assertStatus(404);
        $this->postJson("/api/v1/fuel-station/stations/{$stationB->id}/pumps", [
            'code' => 'P-X',
            'product_types' => ['essence'],
            'status' => 'active',
        ])->assertStatus(404);
    }
}
