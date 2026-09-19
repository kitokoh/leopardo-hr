<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\SolutionActivator;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use App\Modules\HealthManager\Infrastructure\Services\HealthSpecialtySeederService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API structure clinique HealthManager — HC-002 (#7786, BC-30).
 *
 * Couvre : auth 401, solution inactive 403 (fail-closed HC-001), employé
 * lambda 403, flux direction complet (service → salle → lit → spécialité →
 * praticien + spécialités n-n), statut lit (libre/occupé/maintenance),
 * hiérarchie service/salle/lit (422 cross refus), lecture accueil et
 * praticien, isolation cross-tenant 404, seed de spécialités idempotent à
 * l'activation.
 */
class HealthStructureApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

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

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/departments')->assertStatus(401);
        $this->postJson($this->baseUrl().'/beds', [])->assertStatus(401);
    }

    public function test_inactive_solution_gets_403_fail_closed(): void
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
        $this->postJson($this->baseUrl().'/rooms', [])->assertStatus(403);
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/departments')->assertStatus(403);
        $this->postJson($this->baseUrl().'/departments', ['code' => 'x', 'name' => 'X'])->assertStatus(403);
        $this->getJson($this->baseUrl().'/beds')->assertStatus(403);
        $this->getJson($this->baseUrl().'/practitioners')->assertStatus(403);
    }

    public function test_director_manages_full_clinical_structure(): void
    {
        Sanctum::actingAs($this->principalA);

        // Service médical.
        $departmentId = (int) $this->postJson($this->baseUrl().'/departments', [
            'code' => 'CARDIO',
            'name' => 'Cardiologie',
            'description' => 'Service de cardiologie',
        ])->assertStatus(201)->assertJsonPath('data.code', 'CARDIO')->json('data.id');

        // Salle rattachée au service.
        $roomId = (int) $this->postJson($this->baseUrl().'/rooms', [
            'department_id' => $departmentId,
            'code' => 'C-101',
            'name' => 'Salle 101',
            'room_type' => 'hospitalization',
        ])->assertStatus(201)->assertJsonPath('data.department_id', $departmentId)->json('data.id');

        // Lit rattaché à la salle — libre par défaut.
        $bedId = (int) $this->postJson($this->baseUrl().'/beds', [
            'room_id' => $roomId,
            'code' => 'L1',
        ])->assertStatus(201)
            ->assertJsonPath('data.room_id', $roomId)
            ->assertJsonPath('data.status', 'free')
            ->json('data.id');

        // Statut du lit : occupé, puis maintenance.
        $this->putJson($this->baseUrl().'/beds/'.$bedId, ['status' => 'occupied'])
            ->assertStatus(200)->assertJsonPath('data.status', 'occupied');
        $this->putJson($this->baseUrl().'/beds/'.$bedId, ['status' => 'maintenance'])
            ->assertStatus(200)->assertJsonPath('data.status', 'maintenance');
        // Statut inconnu refusé (CHECK + validation).
        $this->putJson($this->baseUrl().'/beds/'.$bedId, ['status' => 'broken'])->assertStatus(422);

        // Spécialité + praticien lié à un employé RH, avec n-n spécialités.
        $specialtyId = (int) $this->postJson($this->baseUrl().'/specialties', [
            'code' => 'cardio_interv',
            'name' => 'Cardiologie interventionnelle',
        ])->assertStatus(201)->json('data.id');

        /** @var Employee $doctor */
        $doctor = Employee::factory()->create(['company_id' => $this->companyA->id]);

        $practitionerId = (int) $this->postJson($this->baseUrl().'/practitioners', [
            'employee_id' => $doctor->id,
            'display_name' => 'Dr Sarah B.',
            'title' => 'Dr',
            'specialty_ids' => [$specialtyId],
        ])->assertStatus(201)
            ->assertJsonPath('data.employee_id', $doctor->id)
            ->assertJsonPath('data.specialties.0.id', $specialtyId)
            ->json('data.id');

        // Listes paginées bornées au tenant.
        $this->getJson($this->baseUrl().'/departments')->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->getJson($this->baseUrl().'/beds?room_id='.$roomId)->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->getJson($this->baseUrl().'/practitioners?specialty_id='.$specialtyId)
            ->assertStatus(200)->assertJsonPath('meta.total', 1);

        // Un même employé ne peut pas être praticien deux fois (422).
        $this->postJson($this->baseUrl().'/practitioners', [
            'employee_id' => $doctor->id,
            'display_name' => 'Doublon',
        ])->assertStatus(422);

        $this->assertGreaterThan(0, $practitionerId);
    }

    public function test_bed_and_room_hierarchy_is_tenant_scoped(): void
    {
        Sanctum::actingAs($this->principalA);

        // Service et salle du tenant B — invisibles/inutilisables depuis A.
        /** @var HealthDepartment $departmentB */
        $departmentB = HealthDepartment::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'code' => 'MAT',
            'name' => 'Maternité',
        ]);
        /** @var HealthRoom $roomB */
        $roomB = HealthRoom::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'department_id' => $departmentB->id,
            'code' => 'M-1',
            'name' => 'Salle M1',
        ]);

        // Créer une salle A rattachée au service de B → 422 (exists scopé).
        $this->postJson($this->baseUrl().'/rooms', [
            'department_id' => $departmentB->id,
            'code' => 'A-1',
            'name' => 'Salle A1',
        ])->assertStatus(422);

        // Créer un lit A rattaché à la salle de B → 422.
        $this->postJson($this->baseUrl().'/beds', [
            'room_id' => $roomB->id,
            'code' => 'L1',
        ])->assertStatus(422);
    }

    public function test_cross_tenant_resources_return_404(): void
    {
        /** @var HealthDepartment $departmentB */
        $departmentB = HealthDepartment::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'code' => 'URG',
            'name' => 'Urgences',
        ]);
        /** @var HealthRoom $roomB */
        $roomB = HealthRoom::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'department_id' => $departmentB->id,
            'code' => 'U-1',
            'name' => 'Salle U1',
        ]);
        /** @var HealthBed $bedB */
        $bedB = HealthBed::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'room_id' => $roomB->id,
            'code' => 'L1',
        ]);

        Sanctum::actingAs($this->principalA);

        $this->getJson($this->baseUrl().'/departments/'.$departmentB->id)->assertStatus(404);
        $this->putJson($this->baseUrl().'/rooms/'.$roomB->id, ['name' => 'Piraté'])->assertStatus(404);
        $this->deleteJson($this->baseUrl().'/beds/'.$bedB->id)->assertStatus(404);
    }

    public function test_reception_and_practitioner_can_read_but_not_manage(): void
    {
        /** @var HealthDepartment $department */
        $department = HealthDepartment::query()->forceCreate([
            'company_id' => $this->companyA->id,
            'code' => 'PED',
            'name' => 'Pédiatrie',
        ]);

        // Accueil (health.reception — rôle d'équipe superviseur).
        /** @var Employee $reception */
        $reception = Employee::factory()->create([
            'company_id' => $this->companyA->id,
            'role' => 'manager',
            'manager_role' => 'superviseur',
        ]);
        Sanctum::actingAs($reception);
        $this->getJson($this->baseUrl().'/departments')->assertStatus(200);
        $this->getJson($this->baseUrl().'/departments/'.$department->id)->assertStatus(200);
        $this->postJson($this->baseUrl().'/departments', ['code' => 'x', 'name' => 'X'])->assertStatus(403);
        $this->deleteJson($this->baseUrl().'/departments/'.$department->id)->assertStatus(403);

        // Praticien actif (health.practitioner) : lecture seule.
        /** @var Employee $doctor */
        $doctor = Employee::factory()->create(['company_id' => $this->companyA->id]);
        HealthPractitioner::query()->forceCreate([
            'company_id' => $this->companyA->id,
            'employee_id' => $doctor->id,
            'display_name' => 'Dr K.',
            'status' => HealthPractitioner::STATUS_ACTIVE,
        ]);
        Sanctum::actingAs($doctor);
        $this->getJson($this->baseUrl().'/departments')->assertStatus(200);
        $this->postJson($this->baseUrl().'/departments', ['code' => 'y', 'name' => 'Y'])->assertStatus(403);
    }

    public function test_specialties_are_seeded_on_activation_idempotently(): void
    {
        /** @var Company $fresh */
        $fresh = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['rh' => true, 'documents' => true, 'notifications' => true],
        ]);

        app(SolutionActivator::class)->activate($fresh, 'healthmanager');

        $count = DB::table('health_specialties')->where('company_id', $fresh->id)->count();
        $this->assertGreaterThanOrEqual(15, $count);

        // Rejouer le seed ne crée jamais de doublon (insertOrIgnore).
        app(HealthSpecialtySeederService::class)->seed($fresh);
        $this->assertSame($count, DB::table('health_specialties')->where('company_id', $fresh->id)->count());
    }
}
