<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelMeterInterval;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelPump;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API notifications & alertes FuelStation — FUEL-019 (issue #5813).
 *
 * Couvre : préférences tenant (upsert, canal désactivable), déduplication
 * par alert_key, scan planifié (anomalie compteur, clôture manquante,
 * maintenance due), cycle acknowledge/resolve, cross-tenant 404.
 */
class FuelAlertApiTest extends TestCase
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
        $this->getJson('/api/v1/fuel-station/alerts')->assertStatus(401);
        $this->getJson('/api/v1/fuel-station/notifications/preferences')->assertStatus(401);
    }

    public function test_operator_cannot_access_alerts(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->getJson('/api/v1/fuel-station/alerts')->assertStatus(403);
        $this->putJson('/api/v1/fuel-station/notifications/preferences', [])->assertStatus(403);
    }

    public function test_preferences_upsert_and_channel_disable(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->putJson('/api/v1/fuel-station/notifications/preferences', [
            'preferences' => [
                ['event_type' => 'stock_variance', 'channel' => 'in_app', 'enabled' => false],
                ['event_type' => 'stock_variance', 'channel' => 'email', 'enabled' => true],
                ['event_type' => 'incident', 'channel' => 'in_app', 'enabled' => true],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.updated', 3);

        $this->getJson('/api/v1/fuel-station/notifications/preferences')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // Re-upsert : mise à jour, pas de doublon.
        $this->putJson('/api/v1/fuel-station/notifications/preferences', [
            'preferences' => [
                ['event_type' => 'stock_variance', 'channel' => 'in_app', 'enabled' => true],
            ],
        ])->assertOk()->assertJsonPath('data.updated', 1);

        $this->assertDatabaseCount('fuel_notification_preferences', 3);
    }

    public function test_scan_command_creates_deduplicated_alerts(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        /** @var FuelPump $pump */
        $pump = FuelPump::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'code' => 'P-01',
            'product_types' => ['essence'],
            'status' => FuelPump::STATUS_ACTIVE,
        ]);

        /** @var FuelMeterRegister $meter */
        $meter = FuelMeterRegister::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'pump_id' => (int) $pump->getAttribute('id'),
            'meter_code' => 'C-01-A',
            'meter_type' => FuelMeterRegister::TYPE_ELECTRONIC,
            'product_code' => 'essence',
            'unit_code' => 'l',
            'precision_scale' => 2,
            'status' => FuelMeterRegister::STATUS_ACTIVE,
        ]);

        // Intervalle en anomalie.
        FuelMeterInterval::query()->create([
            'company_id' => $this->companyA->id,
            'meter_id' => (int) $meter->getAttribute('id'),
            'previous_value_minor' => 20000,
            'current_value_minor' => 15000,
            'delta_minor' => -5000,
            'interval_seconds' => 3600,
            'calculated_at' => now(),
            'calculation_status' => FuelMeterInterval::STATUS_ANOMALY,
        ]);

        // Session de caisse ouverte trop longtemps.
        FuelCashSession::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'opened_by' => $this->manager($this->companyA)->id,
            'opening_balance' => 0,
            'status' => FuelCashSession::STATUS_OPEN,
            'opened_at' => now()->subHours(30),
        ]);

        // Tâche de maintenance due.
        FuelMaintenanceTask::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'task_type' => FuelMaintenanceTask::TYPE_PREVENTIVE,
            'priority' => FuelMaintenanceTask::PRIORITY_HIGH,
            'status' => FuelMaintenanceTask::STATUS_PENDING,
            'title' => 'Contrôle cuve C1',
            'due_at' => now()->addHours(12),
            'created_by' => $this->manager($this->companyA)->id,
        ]);

        $this->artisan('fuel:alerts-scan', ['--max-hours' => 24])
            ->expectsOutputToContain('3 alerte(s) créée(s)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('fuel_alerts', 3);

        // Re-scan : déduplication → aucun doublon.
        $this->artisan('fuel:alerts-scan', ['--max-hours' => 24])->assertExitCode(0);
        $this->assertDatabaseCount('fuel_alerts', 3);

        $this->getJson('/api/v1/fuel-station/alerts')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_alert_acknowledge_and_resolve(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        /** @var FuelAlert $alert */
        $alert = FuelAlert::query()->create([
            'company_id' => $this->companyA->id,
            'station_id' => (int) $station->getAttribute('id'),
            'event_type' => 'incident',
            'severity' => FuelAlert::SEVERITY_HIGH,
            'alert_key' => 'incident:42',
            'payload' => ['incident_id' => 42, 'title' => 'Pompe en surchauffe'],
            'status' => FuelAlert::STATUS_OPEN,
        ]);

        $this->postJson("/api/v1/fuel-station/alerts/{$alert->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');

        $this->postJson("/api/v1/fuel-station/alerts/{$alert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_at', fn ($v): bool => is_string($v));
    }

    public function test_cross_tenant_alert_is_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        /** @var FuelAlert $alertB */
        $alertB = FuelAlert::query()->create([
            'company_id' => $this->companyB->id,
            'event_type' => 'incident',
            'severity' => FuelAlert::SEVERITY_INFO,
            'alert_key' => 'incident:7',
            'payload' => ['incident_id' => 7],
            'status' => FuelAlert::STATUS_OPEN,
        ]);

        $this->postJson("/api/v1/fuel-station/alerts/{$alertB->id}/acknowledge")->assertStatus(404);
    }
}
