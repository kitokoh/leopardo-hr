<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Enums\FuelIncidentStatus;
use App\Modules\FuelStation\Domain\Enums\FuelMaintenanceTaskStatus;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Infrastructure\Services\FuelIncidentService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Incidents, maintenance et tâches FuelStation — FUEL-010 (issue #5804).
 *
 * Critères : workflow audité ; notifications sans exposition PII (aucune
 * donnée personnelle dans les transitions) ; permissions par site/tenant.
 */
class FuelIncidentWorkflowTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    public function test_operator_reports_incident_and_manager_resolves_with_audit(): void
    {
        $company = $this->company();
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($operator);

        // Signalement par l'opérateur.
        $this->postJson('/api/v1/fuel-station/incidents', [
            'equipment_type' => 'pump',
            'equipment_id' => 3,
            'title' => 'Pompe P1 ne démarre plus',
            'description' => 'Aucune mise sous tension constatée.',
            'severity' => 'high',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', FuelIncidentStatus::Open->value)
            ->assertJsonPath('data.reported_by', $operator->id);

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->where('company_id', $company->id)->firstOrFail();

        // L'opérateur ne peut pas assigner (policy deny).
        $this->postJson("/api/v1/fuel-station/incidents/{$incident->id}/assign", [
            'assigned_to' => $manager->id,
        ])->assertForbidden();

        // Manager assigne puis résout.
        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/fuel-station/incidents/{$incident->id}/assign", [
            'assigned_to' => $operator->id,
        ])->assertOk()->assertJsonPath('data.status', FuelIncidentStatus::Assigned->value);

        $this->postJson("/api/v1/fuel-station/incidents/{$incident->id}/transition", [
            'status' => FuelIncidentStatus::Resolved->value,
            'resolution_note' => 'Relais de pompe remplacé.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', FuelIncidentStatus::Resolved->value)
            ->assertJsonPath('data.resolution_note', 'Relais de pompe remplacé.')
            ->assertJsonPath('data.resolved_by', $manager->id);

        // Workflow audité : assignation + transitions tracées, sans PII.
        $audits = AuditLog::query()
            ->where('auditable_type', FuelIncident::class)
            ->where('auditable_id', $incident->id)
            ->orderBy('id')
            ->get();

        self::assertCount(2, $audits);
        self::assertSame(['fuel_incident.assigned', 'fuel_incident.open_to_resolved'], $audits->pluck('action')->all());
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $company->id,
            'title' => 'Incident test',
            'severity' => 'low',
            'status' => FuelIncidentStatus::Open->value,
            'reported_by' => $manager->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Transition incident invalide/');

        // open → resolved est illégal sans passer par in_progress ? Non : open → resolved est
        // autorisé par la machine à états (clôture directe). Testons open → open (no-op) et
        // une transition réellement illégale : resolved → assigned.
        (new FuelIncidentService)->transition($incident, FuelIncidentStatus::Resolved, $manager, 'clôture directe');
        $incident->refresh();
        (new FuelIncidentService)->transition($incident, FuelIncidentStatus::Assigned, $manager);
    }

    public function test_maintenance_task_lifecycle_is_audited(): void
    {
        $company = $this->company();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/fuel-station/maintenance-tasks', [
            'equipment_type' => 'meter',
            'equipment_id' => 1,
            'type' => 'preventive',
            'title' => 'Contrôle trimestriel compteur',
            'priority' => 'medium',
            'assigned_to' => $operator->id,
            'due_date' => '2026-09-30',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', FuelMaintenanceTaskStatus::Open->value)
            ->assertJsonPath('data.assigned_to', $operator->id);

        /** @var FuelMaintenanceTask $task */
        $task = FuelMaintenanceTask::query()->where('company_id', $company->id)->firstOrFail();

        // L'assigné peut achever la tâche ; le workflow est audité.
        Sanctum::actingAs($operator);
        $this->postJson("/api/v1/fuel-station/maintenance-tasks/{$task->id}/complete", [
            'completion_note' => 'Compteur vérifié, aucun écart.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', FuelMaintenanceTaskStatus::Completed->value)
            ->assertJsonPath('data.completed_by', $operator->id);

        self::assertSame(1, AuditLog::query()
            ->where('action', 'fuel_maintenance_task.completed')
            ->where('auditable_id', $task->id)
            ->count());
    }

    public function test_cross_tenant_incident_is_404(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        /** @var FuelIncident $incidentA */
        $incidentA = FuelIncident::query()->create([
            'company_id' => $companyA->id,
            'title' => 'Incident société A',
            'severity' => 'low',
            'status' => FuelIncidentStatus::Open->value,
            'reported_by' => $managerB->id,
        ]);

        Sanctum::actingAs($managerB);

        // Un manager de B ne voit pas l'incident de A (404, pas de fuite).
        $this->getJson("/api/v1/fuel-station/incidents/{$incidentA->id}")->assertNotFound();
        $this->postJson("/api/v1/fuel-station/incidents/{$incidentA->id}/transition", [
            'status' => FuelIncidentStatus::Resolved->value,
        ])->assertNotFound();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/incidents')->assertStatus(401);
    }
}
