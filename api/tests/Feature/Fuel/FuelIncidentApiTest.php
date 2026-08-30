<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Incidents & maintenance FuelStation — FUEL-010 (issue #5804).
 *
 * Couvre : signalement par tout employé (y compris pompiste), RBAC manager
 * sur la gestion, cycle audité open→assigned→in_progress→resolved→closed,
 * transitions illégales 422, notes de résolution obligatoires, 404 sûr
 * cross-tenant, tâches de maintenance + complétion tracée.
 */
class FuelIncidentApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function setupCompany(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-001',
            'name' => 'Centrale',
            'timezone' => 'UTC',
        ]);

        return [$company, $manager, $operator, $station];
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/incidents')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/stations/1/incidents', [])->assertStatus(401);
    }

    public function test_operator_can_report_but_not_manage(): void
    {
        [$company, , $operator, $station] = $this->setupCompany();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/incidents', [
            'title' => 'Pompe P-1 en panne',
            'severity' => 'high',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.reported_by', $operator->id);

        $this->getJson('/api/v1/fuel-station/incidents')->assertStatus(403);
    }

    public function test_full_lifecycle_with_audit_trail(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        /** @var Employee $technician */
        $technician = Employee::factory()->create(['company_id' => $company->id]);

        $incidentId = $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/incidents', [
            'title' => 'Fuite cuve T-1',
            'equipment_type' => 'tank',
            'severity' => 'critical',
        ])->assertStatus(201)->json('data.id');

        $this->postJson('/api/v1/fuel-station/incidents/'.$incidentId.'/assign', ['assigned_to' => $technician->id])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_to', $technician->id);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incidentId.'/start')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson('/api/v1/fuel-station/incidents/'.$incidentId.'/resolve', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('resolution_notes');

        $this->postJson('/api/v1/fuel-station/incidents/'.$incidentId.'/resolve', [
            'resolution_notes' => 'Joint remplacé, cuve re-étalonnée.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_by', $manager->id);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incidentId.'/close')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // La liste du manager montre le cycle complet.
        $this->getJson('/api/v1/fuel-station/incidents?status=closed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'closed');
    }

    public function test_illegal_transition_returns_422(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        $incidentId = $this->postJson('/api/v1/fuel-station/stations/'.$station->id.'/incidents', [
            'title' => 'Test transition',
        ])->json('data.id');

        // close sans resolved → 422
        $this->postJson('/api/v1/fuel-station/incidents/'.$incidentId.'/close')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'FUEL_INCIDENT_BAD_TRANSITION');
    }

    public function test_cross_tenant_incident_is_404(): void
    {
        [$companyA, , , $stationA] = $this->setupCompany();
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);
        /** @var Employee $reporterA */
        $reporterA = Employee::factory()->create(['company_id' => $companyA->id]);

        /** @var FuelIncident $incidentA */
        $incidentA = FuelIncident::query()->create([
            'company_id' => $companyA->id,
            'station_id' => $stationA->id,
            'title' => 'Incident A',
            'reported_by' => $reporterA->id,
            'severity' => 'medium',
        ]);

        Sanctum::actingAs($managerB);
        $this->getJson('/api/v1/fuel-station/incidents/'.$incidentA->id)->assertStatus(404);
        $this->postJson('/api/v1/fuel-station/incidents/'.$incidentA->id.'/start')->assertStatus(404);
    }

    public function test_maintenance_tasks_crud_and_completion(): void
    {
        [$company, $manager, , $station] = $this->setupCompany();
        Sanctum::actingAs($manager);

        $taskId = $this->postJson('/api/v1/fuel-station/maintenance-tasks', [
            'station_id' => $station->id,
            'title' => 'Étalonnage pompe P-1',
            'type' => 'preventive',
            'priority' => 'high',
            'scheduled_for' => '2026-09-01',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->getJson('/api/v1/fuel-station/maintenance-tasks')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/v1/fuel-station/maintenance-tasks/'.$taskId.'/complete')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completed_by', $manager->id);

        // Complétion idempotente.
        $this->postJson('/api/v1/fuel-station/maintenance-tasks/'.$taskId.'/complete')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');
    }
}
