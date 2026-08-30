<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
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
