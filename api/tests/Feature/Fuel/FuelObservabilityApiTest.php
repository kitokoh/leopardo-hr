<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelAlert;
use App\Modules\FuelStation\Domain\Models\FuelOutboxEvent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API observabilité FuelStation — FUEL-020 (issue #5814).
 *
 * Couvre : auth 401, RBAC (employé 403), métriques tenant-scoped (file
 * outbox, alertes ouvertes, fraîcheur des snapshots), valeurs cohérentes.
 */
class FuelObservabilityApiTest extends TestCase
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

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/health/metrics')->assertStatus(401);
    }

    public function test_operator_cannot_access_metrics(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->getJson('/api/v1/fuel-station/health/metrics')->assertStatus(403);
    }

    public function test_metrics_reflect_tenant_state(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        FuelAlert::query()->create([
            'company_id' => $this->companyA->id,
            'event_type' => 'incident',
            'severity' => FuelAlert::SEVERITY_HIGH,
            'alert_key' => 'incident:1',
            'payload' => ['incident_id' => 1],
            'status' => FuelAlert::STATUS_OPEN,
        ]);

        FuelAlert::query()->create([
            'company_id' => $this->companyB->id,
            'event_type' => 'incident',
            'severity' => FuelAlert::SEVERITY_CRITICAL,
            'alert_key' => 'incident:2',
            'payload' => ['incident_id' => 2],
            'status' => FuelAlert::STATUS_OPEN,
        ]);

        FuelOutboxEvent::query()->create([
            'company_id' => $this->companyA->id,
            'event_type' => FuelOutboxEvent::TYPE_SALE_RECORDED,
            'payload' => ['sale_id' => 1],
            'status' => FuelOutboxEvent::STATUS_FAILED,
            'attempts' => 5,
            'available_at' => now(),
            'last_error' => 'permanent: test',
            'idempotency_key' => 'obs-001',
        ]);

        $this->getJson('/api/v1/fuel-station/health/metrics')
            ->assertOk()
            ->assertJsonPath('data.alerts_open', 1)
            ->assertJsonPath('data.outbox_failed', 1)
            ->assertJsonPath('data.outbox_pending', 0)
            ->assertJsonPath('data.readings_today', 0)
            ->assertJsonPath('data.generated_at', fn ($v): bool => is_string($v));
    }

    public function test_metrics_are_tenant_isolated(): void
    {
        Sanctum::actingAs($this->manager($this->companyB));

        // L'alerte du tenant A n'apparaît pas dans les métriques du tenant B.
        FuelAlert::query()->create([
            'company_id' => $this->companyA->id,
            'event_type' => 'incident',
            'severity' => FuelAlert::SEVERITY_CRITICAL,
            'alert_key' => 'incident:3',
            'payload' => ['incident_id' => 3],
            'status' => FuelAlert::STATUS_OPEN,
        ]);

        $this->getJson('/api/v1/fuel-station/health/metrics')
            ->assertOk()
            ->assertJsonPath('data.alerts_open', 0);
    }
}
