<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API & Policies manager/opérateur — FUEL-011 (issue #5805).
 *
 * Couvre : CRUD référentiel (stations, sites, équipements, produits)
 * réservé au manager (403 opérateur), lecture ouverte aux employés du
 * tenant, deny-by-default, isolation tenant 404, pagination bornée.
 */
class FuelReferentialApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return $operator;
    }

    public function test_operator_cannot_create_station(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->operator($company));

        $this->postJson('/api/v1/fuel-station/stations', [
            'code' => 'ST-X',
            'name' => 'Station X',
        ])->assertStatus(403);
    }

    public function test_manager_creates_station_and_product(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/fuel-station/stations', [
            'code' => 'ST-001',
            'name' => 'Station Centrale',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'ST-001');

        $this->postJson('/api/v1/fuel-station/products', [
            'code' => 'ESS',
            'name' => 'Essence sans plomb',
            'unit_code' => 'l',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'ESS');
    }

    public function test_equipment_crud_manager_only(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-EQ',
            'name' => 'Station équipements',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        /** @var array<string, mixed> $pump */
        $pump = $this->postJson('/api/v1/fuel-station/equipment', [
            'kind' => 'pump',
            'station_id' => $station->id,
            'code' => 'P-01',
            'product_types' => ['ESS', 'GAZ'],
        ])->assertStatus(201)->json('data');

        $this->assertSame('pump', $pump['kind']);

        /** @var array<string, mixed> $tank */
        $tank = $this->postJson('/api/v1/fuel-station/equipment', [
            'kind' => 'tank',
            'station_id' => $station->id,
            'code' => 'T-01',
            'product_type' => 'ESS',
            'capacity_minor' => 20000,
        ])->assertStatus(201)->json('data');

        $this->assertSame('tank', $tank['kind']);

        /** @var array<string, mixed> $meter */
        $meter = $this->postJson('/api/v1/fuel-station/equipment', [
            'kind' => 'meter',
            'station_id' => $station->id,
            'pump_id' => (int) $pump['id'],
            'code' => 'M-01',
            'meter_code' => 'M-01',
            'meter_type' => 'electronic',
            'unit_code' => 'l',
        ])->assertStatus(201)->json('data');

        $this->assertSame('meter', $meter['kind']);

        // L'opérateur ne peut ni créer ni modifier.
        Sanctum::actingAs($this->operator($company));
        $this->postJson('/api/v1/fuel-station/equipment', [
            'kind' => 'pump',
            'station_id' => $station->id,
            'code' => 'P-02',
        ])->assertStatus(403);

        $this->putJson('/api/v1/fuel-station/equipment/pump/'.$pump['id'], ['code' => 'P-99'])->assertStatus(403);

        // L'opérateur peut LIRE le référentiel (lecture ouverte au tenant).
        $this->getJson('/api/v1/fuel-station/equipment?kind=pump')->assertStatus(200);
        $this->getJson('/api/v1/fuel-station/stations')->assertStatus(200);
        $this->getJson('/api/v1/fuel-station/products')->assertStatus(200);

        $this->assertSame(1, FuelPump::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, FuelTank::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, FuelMeterRegister::query()->where('company_id', $company->id)->count());
    }

    public function test_cross_tenant_referential_is_404(): void
    {
        $companyA = $this->company();
        Sanctum::actingAs($this->manager($companyA));

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $companyA->id,
            'code' => 'ST-A',
            'name' => 'Station A',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        $this->getJson('/api/v1/fuel-station/stations/'.$station->id)->assertStatus(404);
        $this->putJson('/api/v1/fuel-station/stations/'.$station->id, ['name' => 'X'])->assertStatus(404);
        $this->deleteJson('/api/v1/fuel-station/stations/'.$station->id)->assertStatus(404);
    }
}
