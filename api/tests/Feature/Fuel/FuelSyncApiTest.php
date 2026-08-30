<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Offline & synchronisation — FUEL-014 (issue #5808).
 *
 * Couvre : resynchronisation bornée (since_id), lots de relevés/ventes
 * idempotents (rejeu sans doublon), isolation tenant, événements de lot
 * journalisés dans l'outbox.
 */
class FuelSyncApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return $operator;
    }

    private function station(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-SYNC',
            'name' => 'Station sync',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    private function meter(Company $company, FuelStation $station): FuelMeterRegister
    {
        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'code' => 'P-01',
            'status' => 'active',
        ]);

        /** @var FuelMeterRegister $meter */
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'pump_id' => $pump->id,
            'meter_code' => 'M-01',
            'meter_type' => 'electronic',
            'unit_code' => 'l',
            'status' => 'active',
        ]);

        return $meter;
    }

    public function test_bulk_readings_are_idempotent(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $meter = $this->meter($company, $station);
        Sanctum::actingAs($this->operator($company));

        $payload = [
            'readings' => [[
                'station_id' => $station->id,
                'pump_id' => $meter->pump_id,
                'meter_id' => $meter->id,
                'reading_value_minor' => 150000,
                'idempotency_key' => 'offline-reading-001',
            ]],
        ];

        $this->postJson('/api/v1/fuel-station/sync/readings', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.received', 1)
            ->assertJsonPath('data.duplicates', 0);

        // Rejeu du même lot → 0 reçu, 1 doublon, aucun doublon en base.
        $this->postJson('/api/v1/fuel-station/sync/readings', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.received', 0)
            ->assertJsonPath('data.duplicates', 1);

        $this->assertSame(1, FuelMeterReading::query()->where('company_id', $company->id)->count());
    }

    public function test_bulk_sales_are_idempotent(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        Sanctum::actingAs($this->operator($company));

        $payload = [
            'sales' => [[
                'station_id' => $station->id,
                'product' => 'Essence',
                'quantity' => 20,
                'unit_price' => 150,
                'source' => 'pos',
                'external_id' => 'POS-SYNC-0001',
            ]],
        ];

        $this->postJson('/api/v1/fuel-station/sync/sales', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.received', 1);

        $this->postJson('/api/v1/fuel-station/sync/sales', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.received', 0)
            ->assertJsonPath('data.duplicates', 1);

        $this->assertSame(1, FuelSale::query()->where('company_id', $company->id)->count());
    }

    public function test_outbox_sync_is_bounded(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        Sanctum::actingAs($this->operator($company));

        // Deux ventes créées, puis outbox depuis 0 → les deux remontées.
        $this->postJson('/api/v1/fuel-station/sync/sales', [
            'sales' => [
                ['product' => 'A', 'quantity' => 1, 'unit_price' => 10, 'external_id' => 'E1'],
                ['product' => 'B', 'quantity' => 2, 'unit_price' => 20, 'external_id' => 'E2'],
            ],
        ])->assertStatus(200);

        $outbox = $this->getJson('/api/v1/fuel-station/sync/outbox?since_id=0&limit=10')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $outbox['sales']);
        $this->assertSame('E1', $outbox['sales'][0]['external_id'] ?? $outbox['sales'][0]['external_id']);
    }

    public function test_cross_tenant_reading_rejected(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $stationA = $this->station($companyA);
        $meterA = $this->meter($companyA, $stationA);

        Sanctum::actingAs($this->operator($companyB));

        $this->postJson('/api/v1/fuel-station/sync/readings', [
            'readings' => [[
                'station_id' => $stationA->id,
                'pump_id' => $meterA->pump_id,
                'meter_id' => $meterA->id,
                'reading_value_minor' => 1000,
                'idempotency_key' => 'x-tenant-001',
            ]],
        ])->assertStatus(200)->assertJsonPath('data.received', 0); // résolution tenant-scoped → ignoré

        $this->assertSame(0, FuelMeterReading::query()->where('company_id', $companyB->id)->count());
    }
}
