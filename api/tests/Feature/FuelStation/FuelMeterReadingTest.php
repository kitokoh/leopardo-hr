<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelMeter;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Models\FuelMeterReadingCorrection;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelMeterReadingService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * FUEL-004 — Relevés de compteur par pompe, heure et opérateur (issue #5798).
 *
 * Deux relevés cohérents produisent un delta ; valeur décroissante →
 * anomalie ; correction versionnée et auditée ; zéro doublon (idempotence)
 * et zéro fuite tenant.
 */
class FuelMeterReadingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(): \App\Core\Auth\Domain\Models\Employee
    {
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function meter(Company $company): FuelMeter
    {
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.uniqid('', false),
            'name' => 'Station',
        ]);
        $site = FuelSite::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'code' => 'SITE-'.uniqid('', false),
            'name' => 'Site',
        ]);
        $pump = FuelPump::query()->create([
            'company_id' => $company->id,
            'site_id' => $site->id,
            'code' => 'PMP-'.uniqid('', false),
        ]);

        /** @var FuelMeter $meter */
        $meter = FuelMeter::query()->create([
            'company_id' => $company->id,
            'pump_id' => $pump->id,
            'code' => 'MTR-'.uniqid('', false),
            'is_active' => true,
        ]);

        return $meter;
    }

    public function test_two_coherent_readings_produce_a_delta(): void
    {
        $service = app(FuelMeterReadingService::class);
        $meter = $this->meter($this->companyA);

        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        $first = $service->record($meter, 1000.0, Carbon::parse('2026-08-28T08:00:00Z'));
        $second = $service->record($meter, 1042.5, Carbon::parse('2026-08-28T09:00:00Z'));

        $this->assertNull($first->delta);
        $this->assertSame(42.5, $second->delta);
        $this->assertFalse($second->anomaly);
        $this->assertFalse($second->rollover);
    }

    public function test_decreasing_value_becomes_anomaly(): void
    {
        $service = app(FuelMeterReadingService::class);
        $meter = $this->meter($this->companyA);

        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        $service->record($meter, 5000.0, Carbon::parse('2026-08-28T08:00:00Z'));
        $anomalous = $service->record($meter, 4999.0, Carbon::parse('2026-08-28T09:00:00Z'));

        $this->assertSame(-1.0, $anomalous->delta);
        $this->assertTrue($anomalous->anomaly);
        $this->assertTrue($anomalous->rollover);
    }

    public function test_duplicate_reading_is_idempotent(): void
    {
        $service = app(FuelMeterReadingService::class);
        $meter = $this->meter($this->companyA);

        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        $at = Carbon::parse('2026-08-28T08:00:00Z');
        $first = $service->record($meter, 1000.0, $at);
        $replay = $service->record($meter, 1000.0, $at);

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, FuelMeterReading::query()->withoutGlobalScopes()->where('meter_id', $meter->id)->count());
    }

    public function test_correction_is_versioned_and_audited(): void
    {
        $service = app(FuelMeterReadingService::class);
        $meter = $this->meter($this->companyA);

        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        $reading = $service->record($meter, 1000.0, Carbon::parse('2026-08-28T08:00:00Z'));

        $corrected = $service->correct($reading, 1010.0, 'Relevé erroné, corrigé après vérification', 'user-1');

        $this->assertSame(1010.0, $corrected->reading_value);
        $this->assertDatabaseHas('fuel_meter_reading_corrections', [
            'company_id' => $this->companyA->id,
            'reading_id' => $reading->id,
            'old_value' => 1000,
            'new_value' => 1010,
            'corrected_by' => 'user-1',
        ]);
    }

    public function test_cross_tenant_readings_are_isolated(): void
    {
        $service = app(FuelMeterReadingService::class);
        $meterA = $this->meter($this->companyA);
        $meterB = $this->meter($this->companyB);

        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);
        $service->record($meterA, 100.0, Carbon::parse('2026-08-28T08:00:00Z'));

        app()->instance('current_company', $this->companyB);
        $service->record($meterB, 200.0, Carbon::parse('2026-08-28T08:00:00Z'));

        // Sous le tenant A, on ne voit que les relevés de A.
        app()->instance('current_company', $this->companyA);
        $this->assertSame(1, FuelMeterReading::query()->count());

        // Les relevés du tenant B ne sont pas accessibles via l'API A (404).
        Sanctum::actingAs($this->manager());
        $this->getJson('/api/v1/fuelstation/meters/'.$meterB->id.'/readings')->assertStatus(404);
    }

    public function test_api_records_reading_with_rbac(): void
    {
        Sanctum::actingAs($this->manager());
        $meter = $this->meter($this->companyA);

        $this->postJson('/api/v1/fuelstation/meters/'.$meter->id.'/readings', [
            'reading_value' => 1234.5,
            'reading_at' => '2026-08-28T08:00:00Z',
            'source' => 'api',
            'operator_id' => 'op-1',
        ])->assertCreated()->assertJsonPath('data.reading_value', 1234.5);

        $this->assertDatabaseHas('fuel_meter_readings', [
            'company_id' => $this->companyA->id,
            'meter_id' => $meter->id,
            'source' => 'api',
        ]);
    }

    public function test_employee_cannot_access_readings(): void
    {
        $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);

        $meter = $this->meter($this->companyA);

        $this->getJson('/api/v1/fuelstation/meters/'.$meter->id.'/readings')->assertStatus(403);
    }
}
