<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Synchronisation offline des relevés — FUEL-014 (issue #5808).
 *
 * Couvre : lot de relevés hors-ligne, rejeu idempotent (aucun doublon),
 * horodatage capture/réception distincts, référence hors tenant rejetée
 * sans révéler l'existence, limite de lot.
 */
class FuelReadingSyncApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/v1/fuel-station/readings/sync', [
            'readings' => [],
        ])->assertStatus(401);
    }

    public function test_operator_syncs_offline_batch_idempotently(): void
    {
        [$company, $operator, $station, $pump, $meter] = $this->seedFixture();

        Sanctum::actingAs($operator);

        $payload = [
            'readings' => [
                [
                    'station_id' => $station->id,
                    'pump_id' => $pump->id,
                    'meter_id' => $meter->id,
                    'reading_value_minor' => 10000,
                    'captured_at' => '2026-08-30T08:00:00+01:00',
                    'idempotency_key' => 'offline-reading-001',
                ],
                [
                    'station_id' => $station->id,
                    'pump_id' => $pump->id,
                    'meter_id' => $meter->id,
                    'reading_value_minor' => 10150,
                    'captured_at' => '2026-08-30T09:00:00+01:00',
                    'idempotency_key' => 'offline-reading-002',
                ],
            ],
        ];

        $this->postJson('/api/v1/fuel-station/readings/sync', $payload)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.created', 2)
            ->assertJsonPath('meta.replayed', 0)
            ->assertJsonPath('data.0.status', 'created');

        $this->assertSame(2, FuelMeterReading::query()->count());

        // L'horodatage de capture (horloge appareil) est préservé.
        $first = FuelMeterReading::query()
            ->where('idempotency_key', 'offline-reading-001')
            ->firstOrFail();
        $this->assertSame(
            '2026-08-30T07:00:00+00:00',
            $first->getAttribute('captured_at_utc')->toIso8601String()
        );

        // Rejeu du même lot : idempotent, aucun doublon.
        $this->postJson('/api/v1/fuel-station/readings/sync', $payload)
            ->assertStatus(200)
            ->assertJsonPath('meta.created', 0)
            ->assertJsonPath('meta.replayed', 2);

        $this->assertSame(2, FuelMeterReading::query()->count());
    }

    public function test_sync_rejects_foreign_reference_without_revealing(): void
    {
        [$companyA, $operatorA] = $this->seedFixture();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $stationB = FuelStation::query()->create([
            'company_id' => $companyB->id,
            'code' => 'ST-B',
            'name' => 'Station B',
            'timezone' => 'UTC',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($operatorA);

        $this->postJson('/api/v1/fuel-station/readings/sync', [
            'readings' => [
                [
                    'station_id' => $stationB->id,
                    'pump_id' => 999,
                    'meter_id' => 999,
                    'reading_value_minor' => 100,
                    'idempotency_key' => 'foreign-reading-001',
                ],
            ],
        ])
            ->assertStatus(200)
            ->assertJsonPath('meta.skipped', 1)
            ->assertJsonPath('data.0.error', 'REFERENCE_OUTSIDE_TENANT');

        $this->assertSame(0, FuelMeterReading::query()->count());
    }

    public function test_sync_rejects_invalid_idempotency_key(): void
    {
        [$company, $operator, $station, $pump, $meter] = $this->seedFixture();

        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/readings/sync', [
            'readings' => [
                [
                    'station_id' => $station->id,
                    'pump_id' => $pump->id,
                    'meter_id' => $meter->id,
                    'reading_value_minor' => 100,
                    'idempotency_key' => 'short',
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_sync_rejects_empty_batch(): void
    {
        [$company, $operator] = $this->seedFixture();

        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/readings/sync', [
            'readings' => [],
        ])->assertStatus(422);
    }

    /**
     * @return array{0: Company, 1: Employee, 2: FuelStation, 3: FuelPump, 4: FuelMeterRegister}
     */
    private function seedFixture(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);
        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'code' => 'P-01',
            'product_types' => ['essence'],
            'status' => FuelPump::STATUS_ACTIVE,
        ]);
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'pump_id' => $pump->id,
            'meter_code' => 'M-01-A',
            'meter_type' => FuelMeterRegister::TYPE_ELECTRONIC,
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
            'status' => FuelMeterRegister::STATUS_ACTIVE,
        ]);

        return [$company, $operator, $station, $pump, $meter];
    }
}
