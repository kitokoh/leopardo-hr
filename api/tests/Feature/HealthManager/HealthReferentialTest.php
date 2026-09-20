<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthPractitionerSpecialty;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use App\Modules\HealthManager\Domain\Models\HealthSpecialty;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API référentiel HealthManager — HC-002 (#7786).
 *
 * Matrice canonique (spec §5) : 401 non authentifié, 403 solution inactive
 * (fail-closed, HEALTH_SOLUTION_INACTIVE), 403 employé lambda, happy path
 * direction (CRUD services/salles/lits/spécialités/praticiens/rôles),
 * 404 cross-tenant, lecture seule praticien/réception, sync spécialités,
 * doublon rôle 422, suppression bloquée 422 (HEALTH_RESOURCE_IN_USE).
 */
class HealthReferentialTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $adminA;

    private Employee $lambdaA;

    private function baseUrl(): string
    {
        return '/api/v1/health-manager';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['healthmanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['healthmanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $adminA */
        $adminA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->adminA = $adminA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;
    }

    // ── Fixtures ─────────────────────────────────────────────────────────

    private function makeDepartment(string $companyId, string $code = 'CARDIO'): HealthDepartment
    {
        /** @var HealthDepartment $department */
        $department = HealthDepartment::query()->create([
            'company_id' => $companyId,
            'name' => 'Cardiologie '.$code,
            'code' => $code,
        ]);

        return $department;
    }

    private function makeRoom(HealthDepartment $department, string $code = 'ROOM-1'): HealthRoom
    {
        /** @var HealthRoom $room */
        $room = HealthRoom::query()->create([
            'company_id' => $department->company_id,
            'department_id' => $department->getAttribute('id'),
            'name' => 'Salle '.$code,
            'code' => $code,
        ]);

        return $room;
    }

    private function makeBed(HealthRoom $room, string $code = 'BED-1', string $status = 'free'): HealthBed
    {
        /** @var HealthBed $bed */
        $bed = HealthBed::query()->create([
            'company_id' => $room->company_id,
            'room_id' => $room->getAttribute('id'),
            'code' => $code,
            'status' => $status,
        ]);

        return $bed;
    }

    private function makeSpecialty(string $companyId, string $code): HealthSpecialty
    {
        /** @var HealthSpecialty $specialty */
        $specialty = HealthSpecialty::query()->create([
            'company_id' => $companyId,
            'name' => 'Spécialité '.$code,
            'code' => $code,
        ]);

        return $specialty;
    }

    // ── 401 / 403 fail-closed ────────────────────────────────────────────

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/departments')->assertStatus(401);
        $this->postJson($this->baseUrl().'/practitioners', [])->assertStatus(401);
        $this->postJson($this->baseUrl().'/staff-roles', [])->assertStatus(401);
    }

    public function test_inactive_solution_gets_403(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $inactive->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson($this->baseUrl().'/departments')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/practitioners')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/staff-roles')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        // Payloads VALIDES : la validation FormRequest passe, la policy
        // deny-by-default refuse (403) — pattern EduApiTest.
        $this->getJson($this->baseUrl().'/departments')->assertStatus(403);
        $this->postJson($this->baseUrl().'/departments', ['name' => 'Cardiologie', 'code' => 'CARDIO'])->assertStatus(403);
        $this->getJson($this->baseUrl().'/practitioners')->assertStatus(403);
        $this->postJson($this->baseUrl().'/practitioners', ['employee_id' => $this->lambdaA->id, 'title' => 'dr'])->assertStatus(403);
        $this->getJson($this->baseUrl().'/staff-roles')->assertStatus(403);
        $this->postJson($this->baseUrl().'/staff-roles', ['employee_id' => $this->lambdaA->id, 'role' => 'reception'])->assertStatus(403);
    }

    // ── Happy path direction ─────────────────────────────────────────────

    public function test_admin_department_crud(): void
    {
        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl();

        $departmentId = $this->postJson($url.'/departments', [
            'name' => 'Cardiologie',
            'code' => 'CARDIO',
            'description' => 'Service de cardiologie',
        ])->assertStatus(201)
            ->assertJsonPath('data.name', 'Cardiologie')
            ->assertJsonPath('data.status', 'active')
            ->json('data.id');

        // Code dupliqué par tenant → 422.
        $this->postJson($url.'/departments', ['name' => 'Doublon', 'code' => 'CARDIO'])->assertStatus(422);

        $this->getJson($url.'/departments')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']])
            ->assertJsonPath('meta.total', 1);

        $this->getJson($url.'/departments/'.$departmentId)
            ->assertOk()
            ->assertJsonPath('data.code', 'CARDIO');

        $this->putJson($url.'/departments/'.$departmentId, ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        // Statut hors liste bornée → 422.
        $this->putJson($url.'/departments/'.$departmentId, ['status' => 'bogus'])->assertStatus(422);

        $this->deleteJson($url.'/departments/'.$departmentId)->assertStatus(204);
        $this->assertDatabaseMissing('health_departments', ['id' => $departmentId]);
    }

    public function test_admin_room_and_bed_crud_with_scoped_parent_ids(): void
    {
        $departmentB = $this->makeDepartment($this->companyB->id, 'NEURO-B');

        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl();

        $departmentId = $this->postJson($url.'/departments', [
            'name' => 'Neurologie',
            'code' => 'NEURO',
        ])->assertStatus(201)->json('data.id');

        // FK cross-tenant : service du tenant B refusé (422, exists scopé).
        $this->postJson($url.'/rooms', [
            'department_id' => $departmentB->getAttribute('id'),
            'name' => 'Salle pirate',
            'code' => 'ROOM-X',
        ])->assertStatus(422);

        $roomId = $this->postJson($url.'/rooms', [
            'department_id' => $departmentId,
            'name' => 'Salle 101',
            'code' => 'ROOM-101',
            'type' => 'hospitalization',
        ])->assertStatus(201)->assertJsonPath('data.type', 'hospitalization')->json('data.id');

        $bedId = $this->postJson($url.'/beds', [
            'room_id' => $roomId,
            'code' => 'BED-101-A',
        ])->assertStatus(201)->assertJsonPath('data.status', 'free')->json('data.id');

        $this->getJson($url.'/rooms?department_id='.$departmentId)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($url.'/beds?room_id='.$roomId)->assertOk()->assertJsonPath('meta.total', 1);

        $this->putJson($url.'/beds/'.$bedId, ['status' => 'maintenance'])
            ->assertOk()
            ->assertJsonPath('data.status', 'maintenance');

        $this->deleteJson($url.'/beds/'.$bedId)->assertStatus(204);
        $this->deleteJson($url.'/rooms/'.$roomId)->assertStatus(204);
        $this->deleteJson($url.'/departments/'.$departmentId)->assertStatus(204);
    }

    public function test_admin_specialty_crud(): void
    {
        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl();

        $specialtyId = $this->postJson($url.'/specialties', [
            'name' => 'Cardiologie',
            'code' => 'CARD',
        ])->assertStatus(201)->json('data.id');

        $this->getJson($url.'/specialties')->assertOk()->assertJsonPath('meta.total', 1);

        $this->putJson($url.'/specialties/'.$specialtyId, ['name' => 'Cardiologie interventionnelle'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cardiologie interventionnelle');

        $this->deleteJson($url.'/specialties/'.$specialtyId)->assertStatus(204);
    }

    // ── Praticiens + sync spécialités ────────────────────────────────────

    public function test_practitioner_crud_and_specialty_pivot_sync(): void
    {
        /** @var Employee $doctor */
        $doctor = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $specialtyA = $this->makeSpecialty($this->companyA->id, 'CARD');
        $specialtyB = $this->makeSpecialty($this->companyA->id, 'NEURO');
        $specialtyOther = $this->makeSpecialty($this->companyB->id, 'CARD-B');

        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl();

        // Spécialité du tenant B refusée (422, exists scopé).
        $this->postJson($url.'/practitioners', [
            'employee_id' => $doctor->id,
            'title' => 'dr',
            'specialty_ids' => [$specialtyOther->getAttribute('id')],
        ])->assertStatus(422);

        $practitionerId = $this->postJson($url.'/practitioners', [
            'employee_id' => $doctor->id,
            'title' => 'dr',
            'license_number' => 'LIC-001',
            'specialty_ids' => [$specialtyA->getAttribute('id'), $specialtyB->getAttribute('id')],
        ])->assertStatus(201)
            ->assertJsonPath('data.specialty_ids', [
                (int) $specialtyA->getAttribute('id'),
                (int) $specialtyB->getAttribute('id'),
            ])
            ->json('data.id');

        // Pivot avec company_id du tenant sur chaque ligne.
        $this->assertDatabaseHas('health_practitioner_specialties', [
            'company_id' => $this->companyA->id,
            'practitioner_id' => $practitionerId,
            'specialty_id' => $specialtyA->getAttribute('id'),
        ]);

        // Doublon employé/tenant → 422.
        $this->postJson($url.'/practitioners', ['employee_id' => $doctor->id])->assertStatus(422);

        $this->getJson($url.'/practitioners')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($url.'/practitioners/'.$practitionerId)
            ->assertOk()
            ->assertJsonPath('data.employee_id', $doctor->id);

        // Sync : retire CARD, garde NEURO.
        $this->putJson($url.'/practitioners/'.$practitionerId, [
            'specialty_ids' => [$specialtyB->getAttribute('id')],
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.specialty_ids', [(int) $specialtyB->getAttribute('id')]);

        $this->assertSame(1, HealthPractitionerSpecialty::query()
            ->where('practitioner_id', $practitionerId)
            ->count());

        $this->deleteJson($url.'/practitioners/'.$practitionerId)->assertStatus(204);
        $this->assertDatabaseMissing('health_practitioner_specialties', ['practitioner_id' => $practitionerId]);
    }

    public function test_practitioner_and_reception_can_list_but_not_create(): void
    {
        /** @var Employee $doctorEmployee */
        $doctorEmployee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        HealthPractitioner::query()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => $doctorEmployee->id,
            'title' => 'dr',
            'status' => 'active',
        ]);

        /** @var Employee $receptionEmployee */
        $receptionEmployee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        HealthStaffRole::query()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => $receptionEmployee->id,
            'role' => 'reception',
        ]);

        /** @var Employee $spare */
        $spare = Employee::factory()->create(['company_id' => $this->companyA->id]);

        foreach ([$doctorEmployee, $receptionEmployee] as $actor) {
            Sanctum::actingAs($actor);

            $this->getJson($this->baseUrl().'/departments')->assertOk();
            $this->getJson($this->baseUrl().'/practitioners')->assertOk();
            $this->getJson($this->baseUrl().'/staff-roles')->assertOk();

            // Payloads VALIDES : seul le refus policy (403) est attendu.
            $this->postJson($this->baseUrl().'/departments', ['name' => 'X', 'code' => 'X-'.$actor->id])
                ->assertStatus(403);
            $this->postJson($this->baseUrl().'/practitioners', ['employee_id' => $spare->id, 'title' => 'dr'])
                ->assertStatus(403);
            $this->postJson($this->baseUrl().'/staff-roles', ['employee_id' => $spare->id, 'role' => 'billing'])
                ->assertStatus(403);
        }
    }

    // ── Rôles opérationnels ──────────────────────────────────────────────

    public function test_staff_role_store_validates_and_rejects_duplicates(): void
    {
        /** @var Employee $agent */
        $agent = Employee::factory()->create(['company_id' => $this->companyA->id]);
        /** @var Employee $foreign */
        $foreign = Employee::factory()->create(['company_id' => $this->companyB->id]);

        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl();

        // Rôle hors liste bornée → 422.
        $this->postJson($url.'/staff-roles', ['employee_id' => $agent->id, 'role' => 'doctor'])->assertStatus(422);

        // Employé d'un autre tenant → 422 (exists scopé).
        $this->postJson($url.'/staff-roles', ['employee_id' => $foreign->id, 'role' => 'reception'])->assertStatus(422);

        $staffRoleId = $this->postJson($url.'/staff-roles', ['employee_id' => $agent->id, 'role' => 'reception'])
            ->assertStatus(201)
            ->assertJsonPath('data.role', 'reception')
            ->json('data.id');

        // Doublon employé + rôle + tenant → 422.
        $this->postJson($url.'/staff-roles', ['employee_id' => $agent->id, 'role' => 'reception'])->assertStatus(422);

        // Autre rôle pour le même employé → OK.
        $this->postJson($url.'/staff-roles', ['employee_id' => $agent->id, 'role' => 'billing'])->assertStatus(201);

        $this->getJson($url.'/staff-roles')->assertOk()->assertJsonPath('meta.total', 2);

        $this->deleteJson($url.'/staff-roles/'.$staffRoleId)->assertStatus(204);
        $this->assertDatabaseMissing('health_staff_roles', ['id' => $staffRoleId]);
    }

    // ── Isolation cross-tenant ───────────────────────────────────────────

    public function test_cross_tenant_resources_are_404(): void
    {
        $departmentB = $this->makeDepartment($this->companyB->id, 'CARDIO-B');

        /** @var Employee $doctorB */
        $doctorB = Employee::factory()->create(['company_id' => $this->companyB->id]);
        /** @var HealthPractitioner $practitionerB */
        $practitionerB = HealthPractitioner::query()->create([
            'company_id' => $this->companyB->id,
            'employee_id' => $doctorB->id,
        ]);
        /** @var HealthStaffRole $staffRoleB */
        $staffRoleB = HealthStaffRole::query()->create([
            'company_id' => $this->companyB->id,
            'employee_id' => $doctorB->id,
            'role' => 'reception',
        ]);

        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl();

        $this->getJson($url.'/departments/'.$departmentB->getAttribute('id'))->assertStatus(404);
        $this->putJson($url.'/departments/'.$departmentB->getAttribute('id'), ['name' => 'Hack'])->assertStatus(404);
        $this->deleteJson($url.'/departments/'.$departmentB->getAttribute('id'))->assertStatus(404);

        $this->getJson($url.'/practitioners/'.$practitionerB->getAttribute('id'))->assertStatus(404);
        $this->putJson($url.'/practitioners/'.$practitionerB->getAttribute('id'), ['status' => 'inactive'])->assertStatus(404);
        $this->deleteJson($url.'/practitioners/'.$practitionerB->getAttribute('id'))->assertStatus(404);

        $this->deleteJson($url.'/staff-roles/'.$staffRoleB->getAttribute('id'))->assertStatus(404);

        // Les listes ne fuient rien du tenant B.
        $this->getJson($url.'/departments')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($url.'/practitioners')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($url.'/staff-roles')->assertOk()->assertJsonPath('meta.total', 0);
    }

    // ── Suppression bloquée (HEALTH_RESOURCE_IN_USE) ─────────────────────

    public function test_delete_in_use_returns_422(): void
    {
        $department = $this->makeDepartment($this->companyA->id, 'CHIR');
        $room = $this->makeRoom($department, 'ROOM-201');
        $bed = $this->makeBed($room, 'BED-201-A', 'occupied');
        $specialty = $this->makeSpecialty($this->companyA->id, 'CHIR');

        /** @var Employee $doctor */
        $doctor = Employee::factory()->create(['company_id' => $this->companyA->id]);
        /** @var HealthPractitioner $practitioner */
        $practitioner = HealthPractitioner::query()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => $doctor->id,
        ]);
        HealthPractitionerSpecialty::query()->create([
            'company_id' => $this->companyA->id,
            'practitioner_id' => $practitioner->getAttribute('id'),
            'specialty_id' => $specialty->getAttribute('id'),
        ]);

        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl();

        // Service avec salles → 422.
        $this->deleteJson($url.'/departments/'.$department->getAttribute('id'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_RESOURCE_IN_USE');

        // Salle avec lits → 422.
        $this->deleteJson($url.'/rooms/'.$room->getAttribute('id'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_RESOURCE_IN_USE');

        // Lit occupé → 422.
        $this->deleteJson($url.'/beds/'.$bed->getAttribute('id'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_RESOURCE_IN_USE');

        // Spécialité affectée à un praticien → 422.
        $this->deleteJson($url.'/specialties/'.$specialty->getAttribute('id'))
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_RESOURCE_IN_USE');

        // Une fois la chaîne libérée, la suppression redevient possible.
        $this->putJson($url.'/beds/'.$bed->getAttribute('id'), ['status' => 'free'])->assertOk();
        $this->deleteJson($url.'/beds/'.$bed->getAttribute('id'))->assertStatus(204);
        $this->deleteJson($url.'/rooms/'.$room->getAttribute('id'))->assertStatus(204);
        $this->deleteJson($url.'/departments/'.$department->getAttribute('id'))->assertStatus(204);
    }
}
