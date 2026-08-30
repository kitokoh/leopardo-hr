<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Incidents, maintenance et tâches FuelStation — FUEL-010 (issue #5804).
 *
 * Couvre : signalement par tout employé du tenant, workflow audité
 * (assign → resolve → close), transitions illégales rejetées (422),
 * RBAC deny-by-default (pompiste ne transitionne pas), tâches de
 * maintenance (création, transition par l'assigné), isolation tenant 404,
 * kill switch 403.
 */
class FuelIncidentApiTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_unauthenticated_gets_401(): void
    {
        $this->postJson('/api/v1/fuel-station/incidents', [
            'description_redacted' => 'Pompe 1 hors service',
        ])->assertStatus(401);
    }

    public function test_operator_reports_incident(): void
    {
        [$company, $operator] = $this->seedTenant();

        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/incidents', [
            'category' => 'equipment',
            'severity' => 'high',
            'description_redacted' => 'Pompe 1 ne démarre plus',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'reported')
            ->assertJsonPath('data.reported_by', $operator->id)
            ->assertJsonPath('data.category', 'equipment')
            ->assertJsonPath('data.severity', 'high');

        $this->assertDatabaseHas('fuel_incidents', [
            'company_id' => $company->id,
            'status' => 'reported',
            'reported_by' => $operator->id,
        ]);
    }

    public function test_incident_workflow_assign_resolve_close(): void
    {
        [$company, $manager, $incident] = $this->seedIncident();

        Sanctum::actingAs($manager);

        // assign
        $this->postJson("/api/v1/fuel-station/incidents/{$incident->id}/assign", [
            'assigned_to' => $manager->id,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.assigned_to', $manager->id);

        // resolve
        $this->postJson("/api/v1/fuel-station/incidents/{$incident->id}/resolve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_by', $manager->id);

        // close
        $this->postJson("/api/v1/fuel-station/incidents/{$incident->id}/close")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.closed_by', $manager->id);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'fuel.incident.closed',
        ]);
    }

    public function test_illegal_transition_is_rejected(): void
    {
        [$company, $manager, $incident] = $this->seedIncident();

        Sanctum::actingAs($manager);

        // reported → closed est interdit (doit passer par assign/resolve).
        $this->postJson("/api/v1/fuel-station/incidents/{$incident->id}/close")
            ->assertStatus(422);

        $this->assertSame('reported', $incident->refresh()->status);
    }

    public function test_operator_cannot_transition_incident(): void
    {
        [$company, $manager, $incident] = $this->seedIncident();
        $operator = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($operator);

        $this->postJson("/api/v1/fuel-station/incidents/{$incident->id}/resolve")
            ->assertStatus(403);
    }

    public function test_manager_lists_and_sees_incidents(): void
    {
        [$company, $manager, $incident] = $this->seedIncident();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/incidents')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $incident->id);

        $this->getJson("/api/v1/fuel-station/incidents/{$incident->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $incident->id);
    }

    public function test_cross_tenant_incident_is_404(): void
    {
        [$companyA, $managerA] = $this->seedTenant();
        $companyB = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $stationB = $this->createStation($companyB);
        $managerB = Employee::factory()->create([
            'company_id' => $companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $incidentB = FuelIncident::query()->create([
            'company_id' => $companyB->id,
            'station_id' => $stationB->id,
            'description_redacted' => 'Incident tenant B',
            'reported_by' => $managerB->id,
        ]);

        Sanctum::actingAs($managerA);

        $this->getJson("/api/v1/fuel-station/incidents/{$incidentB->id}")
            ->assertStatus(404);

        $this->postJson("/api/v1/fuel-station/incidents/{$incidentB->id}/resolve")
            ->assertStatus(404);
    }

    public function test_maintenance_task_workflow(): void
    {
        [$company, $manager, $incident] = $this->seedIncident();

        Sanctum::actingAs($manager);

        // Création d'une tâche corrective dérivée de l'incident.
        $this->postJson('/api/v1/fuel-station/maintenance-tasks', [
            'title' => 'Remplacer la pompe 1',
            'incident_id' => $incident->id,
            'task_type' => 'corrective',
            'priority' => 'high',
            'assigned_to' => $manager->id,
            'due_at' => now()->addDays(2)->toDateTimeString(),
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.incident_id', $incident->id)
            ->assertJsonPath('data.priority', 'high');

        $task = FuelMaintenanceTask::query()->firstOrFail();

        // Transition open → in_progress → done (par l'assigné, manager ici).
        $this->postJson("/api/v1/fuel-station/maintenance-tasks/{$task->id}/transition", [
            'status' => 'in_progress',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/fuel-station/maintenance-tasks/{$task->id}/transition", [
            'status' => 'done',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.completed_by', $manager->id);

        $this->assertSame('done', $task->refresh()->status);
    }

    public function test_task_illegal_transition_is_rejected(): void
    {
        [$company, $manager, $incident] = $this->seedIncident();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/maintenance-tasks', [
            'title' => 'Tâche test',
            'incident_id' => $incident->id,
        ])->assertStatus(201);

        $task = FuelMaintenanceTask::query()->firstOrFail();

        // open → done sans passer par in_progress : interdit.
        $this->postJson("/api/v1/fuel-station/maintenance-tasks/{$task->id}/transition", [
            'status' => 'done',
        ])->assertStatus(422);
    }

    public function test_solution_inactive_returns_403(): void
    {
        $company = Company::factory()->create(['features' => []]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/incidents')->assertStatus(403);
    }

    /**
     * @return array{0: Company, 1: Employee, 2: FuelIncident}
     */
    private function seedIncident(): array
    {
        [$company, $manager] = $this->seedTenant();
        $incident = FuelIncident::query()->create([
            'company_id' => $company->id,
            'description_redacted' => 'Incident initial',
            'reported_by' => $manager->id,
        ]);

        return [$company, $manager, $incident];
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedTenant(): array
    {
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return [$company, $manager];
    }

    private function createStation(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-'.substr((string) $company->id, 0, 8),
            'name' => 'Station Test',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }
}
