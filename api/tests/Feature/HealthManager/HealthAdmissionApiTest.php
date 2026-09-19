<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API hospitalisations & occupation des lits — HC-006 (#7790, BC-30).
 *
 * Couvre : 401, solution inactive 403 (fail-closed), employé lambda 403,
 * admission (lit occupé sous transaction, double admission du même lit
 * refusée 409, patient déjà hospitalisé 409), un lit en maintenance non
 * affectable (409), transfert tracé qui libère l'ancien lit, sortie qui
 * libère le lit (notes chiffrées prouvées en base) + séjour sorti terminal
 * (422), vue occupation par service exacte, praticien lit sans gérer,
 * isolation cross-tenant (404, 422).
 */
class HealthAdmissionApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $receptionA;

    private Employee $lambdaA;

    private HealthPatient $patientA;

    private HealthPractitioner $practitionerA;

    private Employee $doctorA;

    private HealthDepartment $departmentA;

    private HealthBed $bed1;

    private HealthBed $bed2;

    private function baseUrl(): string
    {
        return '/api/v1/health-manager/admissions';
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

        /** @var Employee $receptionA */
        $receptionA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'superviseur',
        ]);
        $this->receptionA = $receptionA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        $this->patientA = $this->makePatient($companyA->id, 'PAT-2026-0001', 'Amine', 'Kaci');
        [$this->practitionerA, $this->doctorA] = $this->makePractitioner($companyA->id, 'Dr Sarah B.');

        [$this->departmentA, , $beds] = $this->makeStructure($companyA->id, 'CARDIO', 2);
        [$this->bed1, $this->bed2] = $beds;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl())->assertStatus(401);
        $this->getJson('/api/v1/health-manager/occupancy')->assertStatus(401);
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

        $this->getJson($this->baseUrl())
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
        $this->getJson('/api/v1/health-manager/occupancy')->assertStatus(403);
    }

    public function test_plain_employee_gets_403_on_everything(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl())->assertStatus(403);
        $this->postJson($this->baseUrl(), [])->assertStatus(403);
        $this->getJson('/api/v1/health-manager/occupancy')->assertStatus(403);
    }

    public function test_admission_occupies_bed_and_rejects_double_assignment(): void
    {
        Sanctum::actingAs($this->receptionA);

        $admissionId = (int) $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'department_id' => $this->departmentA->id,
            'bed_id' => $this->bed1->id,
            'reason' => 'Observation post-operatoire',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'admitted')
            ->assertJsonPath('data.bed_id', $this->bed1->id)
            ->json('data.id');

        // Le lit est OCCUPÉ en base, atomiquement avec le séjour.
        $this->assertSame(
            'occupied',
            DB::table('health_beds')->where('id', $this->bed1->id)->value('status')
        );

        // Un lit occupé ne peut pas être réaffecté → 409 (critère HC-006).
        $otherPatient = $this->makePatient($this->companyA->id, 'PAT-2026-0002', 'Lina', 'Benali');
        $this->postJson($this->baseUrl(), [
            'patient_id' => $otherPatient->id,
            'practitioner_id' => $this->practitionerA->id,
            'department_id' => $this->departmentA->id,
            'bed_id' => $this->bed1->id,
            'reason' => 'Double affectation',
        ])->assertStatus(409)->assertJsonPath('error', 'HEALTH_BED_UNAVAILABLE');

        // Un patient n'a qu'UN séjour actif → 409.
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'department_id' => $this->departmentA->id,
            'bed_id' => $this->bed2->id,
            'reason' => 'Doublon patient',
        ])->assertStatus(409)->assertJsonPath('error', 'HEALTH_PATIENT_ALREADY_ADMITTED');

        // Un lit en MAINTENANCE n'est pas affectable → 409.
        $this->bed2->update(['status' => HealthBed::STATUS_MAINTENANCE]);
        $this->postJson($this->baseUrl(), [
            'patient_id' => $otherPatient->id,
            'practitioner_id' => $this->practitionerA->id,
            'department_id' => $this->departmentA->id,
            'bed_id' => $this->bed2->id,
            'reason' => 'Lit en maintenance',
        ])->assertStatus(409)->assertJsonPath('error', 'HEALTH_BED_UNAVAILABLE');

        $this->assertGreaterThan(0, $admissionId);
    }

    public function test_transfer_is_traced_and_frees_previous_bed(): void
    {
        $admission = $this->admit();

        Sanctum::actingAs($this->receptionA);

        // Transfert vers le MÊME lit → 409.
        $this->postJson($this->baseUrl().'/'.$admission->id.'/transfer', ['bed_id' => $this->bed1->id])
            ->assertStatus(409);

        // Transfert vers le lit 2 : tracé, ancien lit LIBÉRÉ.
        $this->postJson($this->baseUrl().'/'.$admission->id.'/transfer', ['bed_id' => $this->bed2->id])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'transferred')
            ->assertJsonPath('data.bed_id', $this->bed2->id)
            ->assertJsonPath('data.transferred_from_bed_id', $this->bed1->id);

        $this->assertSame('free', DB::table('health_beds')->where('id', $this->bed1->id)->value('status'));
        $this->assertSame('occupied', DB::table('health_beds')->where('id', $this->bed2->id)->value('status'));
        $this->assertNotNull(DB::table('health_admissions')->where('id', $admission->id)->value('transferred_at'));

        // Le lit libéré est REPRENABLE par une autre admission.
        $otherPatient = $this->makePatient($this->companyA->id, 'PAT-2026-0002', 'Lina', 'Benali');
        $this->postJson($this->baseUrl(), [
            'patient_id' => $otherPatient->id,
            'practitioner_id' => $this->practitionerA->id,
            'department_id' => $this->departmentA->id,
            'bed_id' => $this->bed1->id,
            'reason' => 'Nouveau sejour',
        ])->assertStatus(201);
    }

    public function test_discharge_frees_bed_and_is_terminal(): void
    {
        $admission = $this->admit();

        Sanctum::actingAs($this->receptionA);

        $this->postJson($this->baseUrl().'/'.$admission->id.'/discharge', [
            'discharge_notes' => 'Sortie sans complication',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'discharged')
            ->assertJsonPath('data.discharge_notes', 'Sortie sans complication');

        // Lit LIBÉRÉ, date de sortie posée, notes CHIFFRÉES au repos.
        $this->assertSame('free', DB::table('health_beds')->where('id', $this->bed1->id)->value('status'));
        $this->assertNotNull(DB::table('health_admissions')->where('id', $admission->id)->value('discharged_at'));
        $rawNotes = (string) DB::table('health_admissions')->where('id', $admission->id)->value('discharge_notes');
        $this->assertNotSame('Sortie sans complication', $rawNotes);
        $this->assertNotEmpty($rawNotes);

        // Un séjour SORTI est terminal : transfert et re-sortie → 422.
        $this->postJson($this->baseUrl().'/'.$admission->id.'/discharge', [])
            ->assertStatus(422)->assertJsonPath('error', 'HEALTH_INVALID_STATUS_TRANSITION');
        $this->postJson($this->baseUrl().'/'.$admission->id.'/transfer', ['bed_id' => $this->bed2->id])
            ->assertStatus(422);

        // Le patient peut être RÉADMIS après sa sortie.
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'department_id' => $this->departmentA->id,
            'bed_id' => $this->bed1->id,
            'reason' => 'Readmission',
        ])->assertStatus(201);
    }

    public function test_occupancy_per_department_is_exact(): void
    {
        // 2e service avec 3 lits : 1 occupé (admission), 1 maintenance, 1 libre.
        [$department2, , $beds2] = $this->makeStructure($this->companyA->id, 'MATER', 3);
        $beds2[1]->update(['status' => HealthBed::STATUS_MAINTENANCE]);

        Sanctum::actingAs($this->receptionA);
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'department_id' => $department2->id,
            'bed_id' => $beds2[0]->id,
            'reason' => 'Suivi',
        ])->assertStatus(201);

        // Structure d'un AUTRE tenant — jamais comptée.
        $this->makeStructure($this->companyB->id, 'CARDIO', 5);

        // Le praticien CONSULTE l'occupation (lecture).
        Sanctum::actingAs($this->doctorA);
        $response = $this->getJson('/api/v1/health-manager/occupancy')->assertStatus(200);

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $byName = collect($data)->keyBy('department_name');
        $cardio = $byName->get('Service CARDIO');
        $mater = $byName->get('Service MATER');

        $this->assertNotNull($cardio);
        $this->assertNotNull($mater);
        $this->assertSame(2, $cardio['total_beds']);
        $this->assertSame(0, $cardio['occupied_beds']);
        $this->assertSame(2, $cardio['free_beds']);
        $this->assertSame(3, $mater['total_beds']);
        $this->assertSame(1, $mater['occupied_beds']);
        $this->assertSame(1, $mater['free_beds']);
        $this->assertSame(1, $mater['maintenance_beds']);
        $this->assertEqualsWithDelta(1 / 3, (float) $mater['occupancy_rate'], 0.001);

        // Le praticien ne GÈRE pas les séjours (403).
        $this->postJson($this->baseUrl(), [])->assertStatus(403);
        $admission = HealthAdmission::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $this->companyA->id)
            ->firstOrFail();
        $this->postJson($this->baseUrl().'/'.$admission->getAttribute('id').'/discharge', [])->assertStatus(403);
    }

    public function test_cross_tenant_is_isolated(): void
    {
        [$departmentB, , $bedsB] = $this->makeStructure($this->companyB->id, 'NEURO', 1);
        $patientB = $this->makePatient($this->companyB->id, 'PAT-2026-0001', 'Sara', 'Alami');
        [$practitionerB] = $this->makePractitioner($this->companyB->id, 'Dr B.');

        Sanctum::actingAs($this->receptionA);

        // Références du tenant B → 422 (Rule::exists scopées).
        $this->postJson($this->baseUrl(), [
            'patient_id' => $patientB->id,
            'practitioner_id' => $practitionerB->id,
            'department_id' => $departmentB->id,
            'bed_id' => $bedsB[0]->id,
            'reason' => 'Intrusion',
        ])->assertStatus(422);

        // Séjour du tenant B → 404 depuis A.
        /** @var HealthAdmission $admissionB */
        $admissionB = HealthAdmission::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'patient_id' => $patientB->id,
            'practitioner_id' => $practitionerB->id,
            'department_id' => $departmentB->id,
            'bed_id' => $bedsB[0]->id,
            'reason' => 'Sejour B',
            'admitted_at' => now(),
            'status' => HealthAdmission::STATUS_ADMITTED,
        ]);

        $this->getJson($this->baseUrl().'/'.$admissionB->id)->assertStatus(404);
        $this->postJson($this->baseUrl().'/'.$admissionB->id.'/transfer', ['bed_id' => $this->bed1->id])->assertStatus(404);
        $this->postJson($this->baseUrl().'/'.$admissionB->id.'/discharge', [])->assertStatus(404);
    }

    private function admit(): HealthAdmission
    {
        Sanctum::actingAs($this->receptionA);

        $admissionId = (int) $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'department_id' => $this->departmentA->id,
            'bed_id' => $this->bed1->id,
            'reason' => 'Observation',
        ])->assertStatus(201)->json('data.id');

        /** @var HealthAdmission $admission */
        $admission = HealthAdmission::query()
            ->withoutGlobalScope('company')
            ->findOrFail($admissionId);

        return $admission;
    }

    private function makePatient(string $companyId, string $mrn, string $firstName, string $lastName): HealthPatient
    {
        /** @var HealthPatient $patient */
        $patient = HealthPatient::query()->forceCreate([
            'company_id' => $companyId,
            'mrn' => $mrn,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);

        return $patient;
    }

    /**
     * @return array{0: HealthPractitioner, 1: Employee}
     */
    private function makePractitioner(string $companyId, string $displayName): array
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $companyId]);

        /** @var HealthPractitioner $practitioner */
        $practitioner = HealthPractitioner::query()->forceCreate([
            'company_id' => $companyId,
            'employee_id' => $employee->id,
            'display_name' => $displayName,
            'status' => HealthPractitioner::STATUS_ACTIVE,
        ]);

        return [$practitioner, $employee];
    }

    /**
     * Service → salle → N lits libres.
     *
     * @return array{0: HealthDepartment, 1: HealthRoom, 2: list<HealthBed>}
     */
    private function makeStructure(string $companyId, string $code, int $bedCount): array
    {
        /** @var HealthDepartment $department */
        $department = HealthDepartment::query()->forceCreate([
            'company_id' => $companyId,
            'code' => $code,
            'name' => 'Service '.$code,
        ]);

        /** @var HealthRoom $room */
        $room = HealthRoom::query()->forceCreate([
            'company_id' => $companyId,
            'department_id' => $department->getAttribute('id'),
            'code' => $code.'-101',
            'name' => 'Salle '.$code,
        ]);

        $beds = [];
        for ($i = 1; $i <= $bedCount; $i++) {
            /** @var HealthBed $bed */
            $bed = HealthBed::query()->forceCreate([
                'company_id' => $companyId,
                'room_id' => $room->getAttribute('id'),
                'code' => 'L'.$i,
                'status' => HealthBed::STATUS_FREE,
            ]);
            $beds[] = $bed;
        }

        return [$department, $room, $beds];
    }
}
