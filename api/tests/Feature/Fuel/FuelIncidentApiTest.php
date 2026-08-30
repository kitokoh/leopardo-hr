<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Incidents, maintenance et tâches FuelStation — FUEL-010 (issue #5804).
 *
 * Couvre : auth 401, signalement par pompiste, RBAC manager, assignation
 * tenant-only (EMPLOYEE_OUTSIDE_TENANT), résolution avec notes
 * obligatoires et idempotente, clôture uniquement après résolution,
 * isolation tenant 404, tâches de maintenance (complétion horodatée),
 * pièces jointes contrôlées (mime allowlist), solution inactive 403.
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
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function station(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-INC-'.random_int(100, 999),
            'name' => 'Station incidents',
            'timezone' => 'Africa/Algiers',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    private function reportPayload(Employee $actor, FuelStation $station): array
    {
        return [
            'station_id' => (int) $station->getAttribute('id'),
            'equipment_type' => 'pump',
            'equipment_id' => 42,
            'title' => 'Pompe P-04 en surchauffe',
            'description' => 'La pompe émet une odeur de brûlé.',
            'priority' => 'high',
        ];
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/fuel-station/incidents')->assertStatus(401);
        $this->postJson('/api/v1/fuel-station/incidents', [])->assertStatus(401);
        $this->getJson('/api/v1/fuel-station/maintenance-tasks')->assertStatus(401);
    }

    public function test_operator_can_report_incident(): void
    {
        $station = $this->station($this->companyA);
        $operator = $this->operator($this->companyA);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/incidents', $this->reportPayload($operator, $station))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'reported')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.reported_by', $operator->id);
    }

    public function test_operator_cannot_list_all_incidents(): void
    {
        $operator = $this->operator($this->companyA);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/fuel-station/incidents')->assertStatus(403);
    }

    public function test_manager_assignes_incident_with_tenant_check(): void
    {
        $station = $this->station($this->companyA);
        $manager = $this->manager($this->companyA);
        $assignee = $this->operator($this->companyA);
        Sanctum::actingAs($manager);

        $incident = $this->postJson('/api/v1/fuel-station/incidents', $this->reportPayload($manager, $station))
            ->assertStatus(200)
            ->json('data');

        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/assign', [
            'assigned_to' => $assignee->id,
        ])->assertStatus(200)->assertJsonPath('data.status', 'assigned');

        // Employé d'un AUTRE tenant refusé.
        $foreign = $this->operator($this->companyB);
        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/assign', [
            'assigned_to' => $foreign->id,
        ])->assertStatus(422)->assertJsonPath('error', 'EMPLOYEE_OUTSIDE_TENANT');
    }

    public function test_resolve_requires_notes_and_is_idempotent(): void
    {
        $station = $this->station($this->companyA);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $incident = $this->postJson('/api/v1/fuel-station/incidents', $this->reportPayload($manager, $station))
            ->json('data');

        // Sans notes → 422 (garde service).
        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/resolve', [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'FUEL_INCIDENT_RESOLUTION_NOTES_REQUIRED');

        // Notes blanches uniquement → 422 (garde service, trim).
        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/resolve', [
            'resolution_notes' => '   ',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'FUEL_INCIDENT_RESOLUTION_NOTES_REQUIRED');

        // Avec notes → resolved.
        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/resolve', [
            'resolution_notes' => 'Remplacé le joint de la pompe.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'resolved');

        // Rejeu → état inchangé (idempotent).
        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/resolve', [
            'resolution_notes' => 'Remplacé le joint de la pompe.',
        ])->assertStatus(200)->assertJsonPath('data.status', 'resolved');
    }

    public function test_close_only_after_resolution(): void
    {
        $station = $this->station($this->companyA);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $incident = $this->postJson('/api/v1/fuel-station/incidents', $this->reportPayload($manager, $station))
            ->json('data');

        // Clôture d'un incident non résolu → 422.
        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/close')
            ->assertStatus(422)
            ->assertJsonPath('error', 'FUEL_INCIDENT_NOT_RESOLVED');

        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/resolve', [
            'resolution_notes' => 'OK',
        ])->assertStatus(200);

        $this->postJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/close')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_cross_tenant_incident_is_404(): void
    {
        $stationB = $this->station($this->companyB);
        $managerB = $this->manager($this->companyB);
        Sanctum::actingAs($managerB);

        $incident = $this->postJson('/api/v1/fuel-station/incidents', $this->reportPayload($managerB, $stationB))
            ->json('data');

        $managerA = $this->manager($this->companyA);
        Sanctum::actingAs($managerA);

        $this->getJson('/api/v1/fuel-station/incidents/'.(int) $incident['id'])
            ->assertStatus(404);
    }

    public function test_maintenance_task_lifecycle(): void
    {
        $station = $this->station($this->companyA);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $incident = $this->postJson('/api/v1/fuel-station/incidents', $this->reportPayload($manager, $station))
            ->json('data');

        $task = $this->postJson('/api/v1/fuel-station/maintenance-tasks', [
            'incident_id' => (int) $incident['id'],
            'title' => 'Remplacement joint P-04',
            'task_type' => 'corrective',
            'scheduled_at' => '2026-08-31 09:00:00',
        ])->assertStatus(201)->assertJsonPath('data.status', 'planned')->json('data');

        $response = $this->putJson('/api/v1/fuel-station/maintenance-tasks/'.(int) $task['id'], [
            'status' => 'completed',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($response->json('data.completed_at'));
    }

    public function test_attachment_mime_allowlist(): void
    {
        $station = $this->station($this->companyA);
        $manager = $this->manager($this->companyA);
        Sanctum::actingAs($manager);

        $incident = $this->postJson('/api/v1/fuel-station/incidents', $this->reportPayload($manager, $station))
            ->json('data');

        // PNG 1×1 réel accepté (allowlist image/*).
        $pngPath = tempnam(sys_get_temp_dir(), 'inc');
        assert(is_string($pngPath));
        file_put_contents(
            $pngPath,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true)
        );

        $this->post('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/attachments', [
            'file' => new UploadedFile($pngPath, 'photo.png', 'image/png', null, true),
        ])->assertStatus(201)->assertJsonPath('data.filename', 'photo.png');

        // Script PHP refusé par l'allowlist mime (aucune exécution possible).
        $evilPath = tempnam(sys_get_temp_dir(), 'evil');
        assert(is_string($evilPath));
        file_put_contents($evilPath, '<?php echo "x";');

        $this->post('/api/v1/fuel-station/incidents/'.(int) $incident['id'].'/attachments', [
            'file' => new UploadedFile($evilPath, 'evil.php', 'application/x-php', null, true),
        ])->assertStatus(422);
    }

    public function test_solution_inactive_returns_403(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create([
            'country' => 'SN',
            'currency' => 'XOF',
            'features' => [],
        ]);
        $manager = $this->manager($inactive);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fuel-station/incidents')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FUEL_SOLUTION_INACTIVE');
    }
}
