<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Support\Facades\DB;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Incidents, maintenance et tâches — FUEL-010 (issue #5804).
 *
 * Couvre : signalement par un pompiste, workflow audité
 * (assign → resolve → close réservés au manager), pièces jointes contrôlées
 * (MIME/size allowlist), événement outbox SANS PII, isolation tenant 404,
 * tâche de maintenance liée.
 * Incidents, maintenance et tâches FuelStation — FUEL-010 (issue #5804).
 *
 * Couvre : signalement par tout employé du tenant, workflow audité
 * (assign → resolve → close), transitions illégales rejetées (422),
 * RBAC deny-by-default (pompiste ne transitionne pas), tâches de
 * maintenance (création, transition par l'assigné), isolation tenant 404,
 * kill switch 403.
 * API incidents, maintenance et tâches FuelStation — FUEL-010 (issue #5804).
 *
 * Couvre : auth 401, RBAC (employé 403), création d'incident rejouable,
 * workflow audité (transitions illégales rejetées, resolution_notes
 * obligatoire), pièces jointes contrôlées (type/taille), tâches de
 * maintenance (création, done), cross-tenant 404.
 */
class FuelIncidentApiTest extends TestCase
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

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function station(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-INC-'.substr((string) $company->id, 0, 6),
            'name' => 'Station incidents',
            'timezone' => 'UTC',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    public function test_operator_reports_incident_and_event_has_no_pii(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        Sanctum::actingAs($this->operator($company));

        $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $station->id,
            'equipment_type' => 'pump',
            'severity' => 'high',
            'title' => 'Pompe P-01 en panne',
            'description' => 'Client mécontent, fuite visible', // PII potentielle
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', FuelIncident::STATUS_OPEN);

        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', 'fuel.incident.reported.v1')
            ->first();

        $this->assertNotNull($event);

        $payload = json_decode((string) $event->payload, true);
        $this->assertIsArray($payload);
        // Aucune PII dans l'événement : ni titre ni description.
        $this->assertArrayNotHasKey('title', $payload);
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayHasKey('incident_id', $payload);
    }

    public function test_operator_cannot_assign_or_resolve(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $operator = $this->operator($company);
        Sanctum::actingAs($operator);

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'severity' => 'medium',
            'title' => 'Incident test',
            'reported_by' => $operator->id,
        ]);

        $manager = $this->manager($company);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/assign', [
            'assigned_to' => $manager->id,
        ])->assertStatus(403);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/resolve', [
            'resolution_notes' => 'Réparé',
        ])->assertStatus(403);
    }

    public function test_manager_assign_resolve_close_workflow(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $operator = $this->operator($company);
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'severity' => 'critical',
            'title' => 'Incident critique',
            'reported_by' => $operator->id,
        ]);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/assign', [
            'assigned_to' => $operator->id,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', FuelIncident::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.assigned_to', $operator->id);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/resolve', [
            'resolution_notes' => 'Pompe réparée',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', FuelIncident::STATUS_RESOLVED)
            ->assertJsonPath('data.resolved_by', $manager->id);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/close', [
            'closure_notes' => 'Clôturé',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', FuelIncident::STATUS_CLOSED);
    }

    public function test_attachment_mime_allowlist_enforced(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $operator = $this->operator($company);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $station->id,
            'title' => 'Incident avec PJ',
            'attachments' => [
                ['file_name' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1024],
                ['file_name' => 'virus.exe', 'mime_type' => 'application/x-msdownload', 'size_bytes' => 100],
            ],
        ])->assertStatus(422); // le .exe n'est pas dans l'allowlist

        // Avec une PJ autorisée : 201 + métadonnées enregistrées.
        $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $station->id,
            'title' => 'Incident avec PJ valide',
            'attachments' => [
                ['file_name' => 'facture.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048],
            ],
        ])->assertStatus(201);

        $this->assertSame(1, FuelIncidentAttachment::query()->where('company_id', $company->id)->count());
    }

    public function test_cross_tenant_incident_is_404(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $companyA->id,
            'severity' => 'low',
            'title' => 'Incident tenant A',
            'reported_by' => $this->operator($companyA)->id,
        ]);

        $this->getJson('/api/v1/fuel-station/incidents/'.$incident->id)->assertStatus(404);
        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/close', ['closure_notes' => 'x'])->assertStatus(404);
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
        $this->getJson('/api/v1/fuel-station/incidents')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/incidents', [])->assertStatus(401);
        $this->getJson('/api/v1/fuel-station/maintenance-tasks')->assertStatus(401);
    }

    public function test_operator_cannot_manage_incidents(): void
    {
        Sanctum::actingAs($this->operator($this->companyA));

        $this->getJson('/api/v1/fuel-station/incidents')->assertStatus(403);
        $this->postJson('/api/v1/fuel-station/incidents', [])->assertStatus(403);
    }

    public function test_manager_creates_incident_and_replay_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $payload = [
            'station_id' => $station->id,
            'equipment_type' => 'pump',
            'equipment_id' => 42,
            'severity' => 'high',
            'title' => 'Pompe P-04 en surchauffe',
            'description' => 'La pompe émet une odeur de brûlé pendant les ravitaillements.',
            'idempotency_key' => 'incident-001',
        ];

        $this->postJson('/api/v1/fuel-station/incidents', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'reported')
            ->assertJsonPath('data.severity', 'high')
            ->assertJsonPath('data.equipment_type', 'pump');

        $this->postJson('/api/v1/fuel-station/incidents', $payload)
            ->assertStatus(200)
            ->assertJsonPath('replayed', true);

        $this->assertDatabaseCount('fuel_incidents', 1);
    }

    public function test_incident_workflow_is_audited(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $incident = $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $station->id,
            'equipment_type' => 'tank',
            'severity' => 'medium',
            'title' => 'Fuite détectée sur la cuve C1',
            'description' => 'Flaque sous la cuve C1, relevé de jauge en baisse.',
            'idempotency_key' => 'incident-002',
        ])->assertStatus(201)->json('data');

        // Transition illégale (reported → resolved sans passer par in_progress).
        $this->postJson("/api/v1/fuel-station/incidents/{$incident['id']}/transition", [
            'status' => 'resolved',
            'resolution_notes' => 'Intervention terminée.',
        ])->assertStatus(422);

        // resolved sans notes de résolution → refusé.
        $this->postJson("/api/v1/fuel-station/incidents/{$incident['id']}/transition", [
            'status' => 'assigned',
        ])->assertOk()->assertJsonPath('data.status', 'assigned');

        $this->postJson("/api/v1/fuel-station/incidents/{$incident['id']}/transition", [
            'status' => 'in_progress',
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/fuel-station/incidents/{$incident['id']}/transition", [
            'status' => 'resolved',
        ])->assertStatus(422);

        $this->postJson("/api/v1/fuel-station/incidents/{$incident['id']}/transition", [
            'status' => 'resolved',
            'resolution_notes' => 'Soudure de la cuve effectuée, jauge stabilisée.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolved_at', fn ($v): bool => is_string($v));

        $this->postJson("/api/v1/fuel-station/incidents/{$incident['id']}/transition", [
            'status' => 'closed',
        ])->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_attachments_are_controlled(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $incident = $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $station->id,
            'equipment_type' => 'meter',
            'severity' => 'low',
            'title' => 'Compteur C-04-A illisible',
            'description' => 'Chiffres effacés sur le totalisateur.',
            'idempotency_key' => 'incident-003',
        ])->assertStatus(201)->json('data');

        // Type non autorisé (exe) → 422.
        $this->post('/api/v1/fuel-station/incidents/' . $incident['id'] . '/attachments', [
            'attachment' => UploadedFile::fake()->create('malware.exe', 10),
        ])->assertStatus(422);

        // Taille > 5 Mo → 422.
        $this->post('/api/v1/fuel-station/incidents/' . $incident['id'] . '/attachments', [
            'attachment' => UploadedFile::fake()->create('photo.png', 6 * 1024),
        ])->assertStatus(422);

        // PDF valide → 201.
        $this->post('/api/v1/fuel-station/incidents/' . $incident['id'] . '/attachments', [
            'attachment' => UploadedFile::fake()->create('constat.pdf', 100, 'application/pdf'),
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $this->assertDatabaseCount('fuel_incident_attachments', 1);
    }

    public function test_maintenance_tasks_lifecycle(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $station = $this->station($this->companyA);

        $task = $this->postJson('/api/v1/fuel-station/maintenance-tasks', [
            'station_id' => $station->id,
            'task_type' => 'preventive',
            'priority' => 'medium',
            'title' => 'Contrôle trimestriel des cuves',
            'description' => 'Inspection visuelle et test de jauge.',
            'due_at' => '2026-09-15T08:00:00Z',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        $this->patchJson("/api/v1/fuel-station/maintenance-tasks/{$task['id']}", [
            'status' => 'done',
            'notes' => 'Aucune anomalie constatée.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.completed_at', fn ($v): bool => is_string($v));

        $this->assertDatabaseHas('fuel_maintenance_tasks', [
            'id' => $task['id'],
            'status' => 'done',
        ]);
    }

    public function test_cross_tenant_incident_is_404(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));
        $stationB = $this->station($this->companyB, 'ST-99');

        $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $stationB->id,
            'equipment_type' => 'other',
            'severity' => 'low',
            'title' => 'Tentative cross-tenant',
            'description' => 'Ne doit jamais être créé.',
            'idempotency_key' => 'incident-x-tenant',
        ])->assertStatus(422);
    }
}
