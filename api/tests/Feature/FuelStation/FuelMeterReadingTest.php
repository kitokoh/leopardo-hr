<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5798 (FUEL-004) — relevés de compteur par pompe, heure et
 * opérateur (spec §13).
 *
 * Couvre : deux relevés cohérents → delta ; valeur décroissante →
 * anomalie ; rollover documenté ; idempotence (zéro doublon) ; correction
 * versionnée et auditée ; cross-tenant 404 ; solution inactive 403 ;
 * RBAC corrections (manager principal/rh).
 */
class FuelMeterReadingTest extends TestCase
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

    private function employee(Company $company, string $role = 'employee'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $role === 'manager' ? 'principal' : null,
            'status' => 'active',
        ]);

        return $employee;
    }

    private function fixture(Company $company): array
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-ABC',
            'name' => 'Station ABC',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'P-04',
            'product_types' => ['essence'],
            'status' => FuelPump::STATUS_ACTIVE,
        ]);

        /** @var FuelMeterRegister $meter */
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $company->id,
            'station_id' => (int) $station->getAttribute('id'),
            'pump_id' => (int) $pump->getAttribute('id'),
            'meter_code' => 'C-04-A',
            'meter_type' => FuelMeterRegister::TYPE_ELECTRONIC,
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
            'status' => FuelMeterRegister::STATUS_ACTIVE,
        ]);

        return [$station, $pump, $meter];
    }

    private function readingsUrl(FuelStation $station, FuelPump $pump, FuelMeterRegister $meter): string
    {
        return '/api/v1/fuel-station/stations/'.(int) $station->getAttribute('id')
            .'/pumps/'.(int) $pump->getAttribute('id')
            .'/meters/'.(int) $meter->getAttribute('id').'/readings';
    }

    /** @return \Illuminate\Testing\TestResponse */
    private function recordReading(
        Company $company,
        FuelStation $station,
        FuelPump $pump,
        FuelMeterRegister $meter,
        int $valueMinor,
        string $key,
        ?string $capturedAt = null,
        ?int $rolloverLimit = null,
    ): TestResponse {
        if ($rolloverLimit !== null) {
            $meter->forceFill(['rollover_limit' => $rolloverLimit])->save();
        }

        return $this->postJson($this->readingsUrl($station, $pump, $meter), [
            'reading_value_minor' => $valueMinor,
            'captured_at' => $capturedAt,
            'idempotency_key' => $key,
        ]);
    }

    public function test_two_coherent_readings_produce_a_valid_delta(): void
    {
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $first = $this->recordReading($this->companyA, $station, $pump, $meter, 12543020, 'reading-0001', '2026-08-28T08:00:00+01:00');
        $first->assertStatus(201);
        $first->assertJsonPath('data.reading.status', 'accepted');
        $first->assertJsonPath('data.interval', null); // premier relevé : pas de delta

        $second = $this->recordReading($this->companyA, $station, $pump, $meter, 12561280, 'reading-0002', '2026-08-28T16:00:00+01:00');
        $second->assertStatus(201);

        // 125 612,80 − 125 430,20 = 182,60 litres → 18 260 unités mineures.
        $this->assertSame(18260, (int) $second->json('data.interval.delta_minor'));
        $this->assertSame('valid', $second->json('data.interval.calculation_status'));

        // L'intervalle est persisté.
        $this->assertDatabaseHas('fuel_meter_intervals', [
            'company_id' => $this->companyA->id,
            'calculation_status' => 'valid',
            'delta_minor' => 18260,
        ]);
    }

    public function test_decreasing_value_becomes_anomaly(): void
    {
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $this->recordReading($this->companyA, $station, $pump, $meter, 12543020, 'reading-0010', '2026-08-28T08:00:00+01:00')->assertStatus(201);

        // Valeur décroissante SANS rollover documenté → anomalie.
        $second = $this->recordReading($this->companyA, $station, $pump, $meter, 12500000, 'reading-0011', '2026-08-28T16:00:00+01:00');
        $second->assertStatus(201);
        $second->assertJsonPath('data.reading.status', 'submitted');
        $second->assertJsonPath('data.interval.calculation_status', 'pending_review');
        $this->assertTrue((int) $second->json('data.interval.delta_minor') < 0);
    }

    public function test_documented_rollover_is_accepted(): void
    {
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $this->recordReading($this->companyA, $station, $pump, $meter, 99990000, 'reading-0020', '2026-08-28T08:00:00+01:00', rolloverLimit: 100000000)->assertStatus(201);

        // Rollover documenté (limite 1 000 000,00) : la valeur repasse sous la limite.
        $second = $this->recordReading($this->companyA, $station, $pump, $meter, 5000000, 'reading-0021', '2026-08-28T16:00:00+01:00', rolloverLimit: 100000000);
        $second->assertStatus(201);
        $second->assertJsonPath('data.interval.calculation_status', 'rollover');
        // delta = (100 000 000 − 99 990 000) + 5 000 000 = 5 100 000
        $this->assertSame(5100000, (int) $second->json('data.interval.delta_minor'));
    }

    public function test_same_idempotency_key_replays_without_duplicates(): void
    {
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $first = $this->recordReading($this->companyA, $station, $pump, $meter, 12543020, 'reading-0030', '2026-08-28T08:00:00+01:00');
        $first->assertStatus(201);

        // Rejeu avec la même clé → 200, replayed, aucun doublon.
        $replay = $this->recordReading($this->companyA, $station, $pump, $meter, 12543020, 'reading-0030', '2026-08-28T08:00:00+01:00');
        $replay->assertStatus(200);
        $replay->assertJsonPath('data.replayed', true);
        $replay->assertJsonPath('data.reading.id', $first->json('data.reading.id'));

        $this->assertDatabaseCount('fuel_meter_readings', 1);
    }

    public function test_correction_is_versioned_and_audited(): void
    {
        Sanctum::actingAs($this->employee($this->companyA, 'manager'));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        $recorded = $this->recordReading($this->companyA, $station, $pump, $meter, 12543020, 'reading-0040', '2026-08-28T08:00:00+01:00');
        $readingId = (int) $recorded->json('data.reading.id');

        $correction = $this->postJson('/api/v1/fuel-station/meter-readings/'.$readingId.'/corrections', [
            'reading_value_minor' => 12543030,
            'reason' => 'Erreur de saisie (1 unité).',
        ]);

        $correction->assertStatus(200);

        // L'original est marqué 'corrected', une nouvelle version existe.
        $this->assertDatabaseHas('fuel_meter_readings', [
            'id' => $readingId,
            'status' => 'corrected',
        ]);
        $this->assertDatabaseHas('fuel_meter_readings', [
            'company_id' => $this->companyA->id,
            'source_code' => 'correction',
            'reading_value_minor' => 12543030,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'fuel.reading.corrected',
        ]);
    }

    public function test_cross_tenant_station_returns_404(): void
    {
        Sanctum::actingAs($this->employee($this->companyA));
        [$foreignStation, $foreignPump, $foreignMeter] = $this->fixture($this->companyB);

        $this->postJson($this->readingsUrl($foreignStation, $foreignPump, $foreignMeter), [
            'reading_value_minor' => 1000,
            'idempotency_key' => 'reading-0050',
        ])->assertStatus(404);
    }

    public function test_future_reading_is_rejected(): void
    {
        Sanctum::actingAs($this->employee($this->companyA));
        [$station, $pump, $meter] = $this->fixture($this->companyA);

        // Dérive d'horloge > 5 min → 422 FUEL_READING_FUTURE.
        $future = now()->addHours(2)->toIso8601String();

        $this->recordReading($this->companyA, $station, $pump, $meter, 1000, 'reading-0060', $future)
            ->assertStatus(422)
            ->assertJsonPath('error', 'FUEL_READING_FUTURE');
    }

    public function test_inactive_solution_returns_403(): void
    {
        // Tenant SANS le flag fuel_station.
        /** @var Company $inactive */
        $inactive = Company::factory()->create([
            'country' => 'TN',
            'currency' => 'TND',
            'features' => [],
        ]);

        Sanctum::actingAs($this->employee($inactive));
        [$station, $pump, $meter] = $this->fixture($inactive);

        $this->postJson($this->readingsUrl($station, $pump, $meter), [
            'reading_value_minor' => 1000,
            'idempotency_key' => 'reading-0070',
        ])->assertStatus(403);
    }

    public function test_ordinary_employee_cannot_correct(): void
    {
        Sanctum::actingAs($this->employee($this->companyA, 'manager'));
        [$station, $pump, $meter] = $this->fixture($this->companyA);
        $recorded = $this->recordReading($this->companyA, $station, $pump, $meter, 1000, 'reading-0080', '2026-08-28T08:00:00+01:00');
        $readingId = (int) $recorded->json('data.reading.id');

        // Employé ordinaire → 403 (Policy correct = manager principal/rh).
        Sanctum::actingAs($this->employee($this->companyA));
        $this->postJson('/api/v1/fuel-station/meter-readings/'.$readingId.'/corrections', [
            'reading_value_minor' => 1100,
            'reason' => 'Test RBAC',
        ])->assertStatus(403);
    }
}
