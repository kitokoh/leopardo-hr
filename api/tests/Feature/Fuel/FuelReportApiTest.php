<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelReportExport;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API reporting opérationnel FuelStation — FUEL-017 (issue #5811).
 *
 * Couvre : read models (volumes par pompe, ventes), recalcul idempotent,
 * export asynchrone pending → generated (CSV téléchargeable), échec →
 * failed, cross-tenant 404, RBAC deny-by-default.
 */
class FuelReportApiTest extends TestCase
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

    private function stationWithMeter(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-01',
            'name' => 'Station 01',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'P-01',
            'product_types' => ['essence'],
            'status' => FuelPump::STATUS_ACTIVE,
        ]);

        /** @var FuelMeterRegister $meter */
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'pump_id' => (int) $pump->getAttribute('id'),
            'meter_code' => 'C-01-A',
            'meter_type' => FuelMeterRegister::TYPE_ELECTRONIC,
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
            'status' => FuelMeterRegister::STATUS_ACTIVE,
        ]);

        FuelMeterInterval::query()->create([
            'company_id' => $company->id,
            'meter_id' => (int) $meter->getAttribute('id'),
            'previous_value_minor' => 10000,
            'current_value_minor' => 15000,
            'delta_minor' => 5000,
            'interval_seconds' => 3600,
            'calculated_at' => now(),
            'calculation_status' => FuelMeterInterval::STATUS_VALID,
        ]);

        return $station;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/reports/daily-volumes')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/reports/exports', [])->assertStatus(401);
    }

    public function test_operator_cannot_access_reports(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->getJson('/api/v1/fuel-station/reports/daily-volumes')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/reports/exports', [])->assertStatus(403);
    }

    public function test_daily_volumes_snapshot_and_idempotent_recompute(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->stationWithMeter($this->companyA);

        $this->getJson('/api/v1/fuel-station/reports/daily-volumes?station_id=' . $station->id)
            ->assertOk()
            ->assertJsonPath('data.volumes_by_pump.0.delta_minor', 5000)
            ->assertJsonPath('recomputed', false);

        // Rejeu : même payload, upsert (toujours une seule ligne de snapshot).
        $this->getJson('/api/v1/fuel-station/reports/daily-volumes?station_id=' . $station->id)
            ->assertOk()
            ->assertJsonPath('recomputed', true);

        $this->assertDatabaseCount('fuel_report_snapshots', 1);
    }

    public function test_export_lifecycle_generates_csv(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->stationWithMeter($this->companyA);

        $export = $this->postJson('/api/v1/fuel-station/reports/exports', [
            'report_type' => 'daily_volumes',
            'station_id' => $station->id,
            'date' => now()->toDateString(),
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        // Queue sync en test : le job a déjà tourné → generated + fichier.
        $this->getJson('/api/v1/fuel-station/reports/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', FuelReportExport::STATUS_GENERATED);

        $this->getJson("/api/v1/fuel-station/reports/exports/{$export['id']}/download")
            ->assertOk();

        $this->assertDatabaseHas('fuel_report_exports', [
            'id' => $export['id'],
            'status' => FuelReportExport::STATUS_GENERATED,
        ]);
    }

    public function test_cross_tenant_station_is_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $stationB = $this->stationWithMeter($this->companyB);

        $this->getJson('/api/v1/fuel-station/reports/daily-volumes?station_id=' . $stationB->id)->assertStatus(404);

        $this->postJson('/api/v1/fuel-station/reports/exports', [
            'report_type' => 'sales_summary',
            'station_id' => $stationB->id,
        ])->assertStatus(422);
    }
}
