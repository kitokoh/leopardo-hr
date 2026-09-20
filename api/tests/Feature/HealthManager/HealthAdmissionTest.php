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
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API hospitalisations & lits — HC-006 (#7790, BC-30).
 *
 * Couvre : matrice 401 / 403 solution inactive / 403 lambda / praticien en
 * LECTURE seule ; machine à états des lits (spec §4) : admission occupe le
 * lit, double admission sur lit occupé → 409 HEALTH_BED_OCCUPIED,
 * transfert libère l'ancien + occupe le nouveau, sortie libère le lit,
 * double sortie → 422 ; occupation par service (taux exacts) ; 404
 * cross-tenant fail-closed.
 */
class HealthAdmissionTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyB;

    private Employee $adminA;

    private Employee $lambdaA;

    private Employee $receptionA;

    private Employee $practitionerEmployeeA;

    private HealthPractitioner $practitionerA;

    private HealthPatient $patientA;

    private HealthDepartment $cardiologyA;

    private HealthDepartment $surgeryA;

    private HealthBed $bedCardio1;

    private HealthBed $bedCardio2;

    private HealthBed $bedSurgery1;

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

        /** @var Employee $receptionA */
        $receptionA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->receptionA = $receptionA;
        HealthStaffRole::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => (int) $receptionA->getAttribute('id'),
            'role' => HealthStaffRole::ROLE_RECEPTION,
        ]);

        /** @var Employee $practitionerEmployeeA */
        $practitionerEmployeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->practitionerEmployeeA = $practitionerEmployeeA;
        /** @var HealthPractitioner $practitionerA */
        $practitionerA = HealthPractitioner::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => (int) $practitionerEmployeeA->getAttribute('id'),
            'title' => HealthPractitioner::TITLE_DR,
            'status' => HealthPractitioner::STATUS_ACTIVE,
        ]);
        $this->practitionerA = $practitionerA;

        /** @var HealthPatient $patientA */
        $patientA = HealthPatient::query()->create([
            'company_id' => $companyA->id,
            'mrn' => 'PAT-2026-0001',
            'full_name' => 'Amine Kaci',
            'sex' => HealthPatient::SEX_MALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);
        $this->patientA = $patientA;

        // 2 services, 3 lits : Cardiologie (2 lits) + Chirurgie (1 lit).
        $this->cardiologyA = $this->makeDepartment($companyA, 'A-Cardiologie', 'CARDIO');
        $this->surgeryA = $this->makeDepartment($companyA, 'B-Chirurgie', 'CHIR');
        $roomCardio = $this->makeRoom($this->cardiologyA, 'CARDIO-S1');
        $roomSurgery = $this->makeRoom($this->surgeryA, 'CHIR-S1');
        $this->bedCardio1 = $this->makeBed($roomCardio, 'CARDIO-L1');
        $this->bedCardio2 = $this->makeBed($roomCardio, 'CARDIO-L2');
        $this->bedSurgery1 = $this->makeBed($roomSurgery, 'CHIR-L1');
    }

    private function makeDepartment(Company $company, string $name, string $code): HealthDepartment
    {
        /** @var HealthDepartment $department */
        $department = HealthDepartment::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'status' => HealthDepartment::STATUS_ACTIVE,
        ]);

        return $department;
    }

    private function makeRoom(HealthDepartment $department, string $code): HealthRoom
    {
        /** @var HealthRoom $room */
        $room = HealthRoom::query()->create([
            'company_id' => $department->company_id,
            'department_id' => (int) $department->getAttribute('id'),
            'name' => 'Salle '.$code,
            'code' => $code,
            'type' => HealthRoom::TYPE_HOSPITALIZATION,
            'status' => HealthRoom::STATUS_ACTIVE,
        ]);

        return $room;
    }

    private function makeBed(HealthRoom $room, string $code): HealthBed
    {
        /** @var HealthBed $bed */
        $bed = HealthBed::query()->create([
            'company_id' => $room->company_id,
            'room_id' => (int) $room->getAttribute('id'),
            'code' => $code,
            'status' => HealthBed::STATUS_FREE,
        ]);

        return $bed;
    }

    /**
     * @return array<string, mixed>
     */
    private function admitPayload(?int $bedId = null): array
    {
        return [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'practitioner_id' => (int) $this->practitionerA->getAttribute('id'),
            'bed_id' => $bedId ?? (int) $this->bedCardio1->getAttribute('id'),
            'reason' => 'Surveillance post-opératoire',
        ];
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/admissions')->assertStatus(401);
        $this->postJson($this->baseUrl().'/admissions', [])->assertStatus(401);
        $this->getJson($this->baseUrl().'/admissions/occupancy')->assertStatus(401);
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

        $this->getJson($this->baseUrl().'/admissions')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/admissions/occupancy')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/admissions')->assertStatus(403);
        $this->postJson($this->baseUrl().'/admissions', $this->admitPayload())->assertStatus(403);
        $this->getJson($this->baseUrl().'/admissions/occupancy')->assertStatus(403);
    }

    public function test_practitioner_can_view_but_not_manage(): void
    {
        Sanctum::actingAs($this->practitionerEmployeeA);

        $this->getJson($this->baseUrl().'/admissions')->assertStatus(200);
        $this->getJson($this->baseUrl().'/admissions/occupancy')->assertStatus(200);
        $this->postJson($this->baseUrl().'/admissions', $this->admitPayload())->assertStatus(403);
    }

    public function test_admit_occupies_bed_and_second_admit_gets_409(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl().'/admissions';
        $bedId = (int) $this->bedCardio1->getAttribute('id');

        $this->postJson($url, $this->admitPayload($bedId))
            ->assertStatus(201)
            ->assertJsonPath('data.status', HealthAdmission::STATUS_ADMITTED)
            ->assertJsonPath('data.bed_id', $bedId)
            // Le service de rattachement est dérivé du lit (salle → service).
            ->assertJsonPath('data.department_id', (int) $this->cardiologyA->getAttribute('id'));

        $this->assertSame(HealthBed::STATUS_OCCUPIED, $this->bedCardio1->refresh()->status);

        // Double admission sur le MÊME lit : 409 HEALTH_BED_OCCUPIED.
        $this->postJson($url, $this->admitPayload($bedId))
            ->assertStatus(409)
            ->assertJsonPath('error', 'HEALTH_BED_OCCUPIED');
    }

    public function test_transfer_frees_old_bed_and_occupies_new_one(): void
    {
        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl().'/admissions';

        $admissionId = (int) $this->postJson($url, $this->admitPayload())->assertStatus(201)->json('data.id');
        $newBedId = (int) $this->bedSurgery1->getAttribute('id');

        $this->postJson($url.'/'.$admissionId.'/transfer', ['bed_id' => $newBedId])
            ->assertStatus(200)
            ->assertJsonPath('data.status', HealthAdmission::STATUS_TRANSFERRED)
            ->assertJsonPath('data.bed_id', $newBedId)
            ->assertJsonPath('data.department_id', (int) $this->surgeryA->getAttribute('id'));

        // Atomique : ancien lit libéré, nouveau occupé.
        $this->assertSame(HealthBed::STATUS_FREE, $this->bedCardio1->refresh()->status);
        $this->assertSame(HealthBed::STATUS_OCCUPIED, $this->bedSurgery1->refresh()->status);

        // Transfert vers un lit occupé : 409 HEALTH_BED_OCCUPIED.
        $otherAdmissionId = (int) $this->postJson($url, array_merge(
            $this->admitPayload((int) $this->bedCardio2->getAttribute('id'))
        ))->assertStatus(201)->json('data.id');

        $this->postJson($url.'/'.$otherAdmissionId.'/transfer', ['bed_id' => $newBedId])
            ->assertStatus(409)
            ->assertJsonPath('error', 'HEALTH_BED_OCCUPIED');
    }

    public function test_discharge_frees_bed_and_double_discharge_gets_422(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl().'/admissions';

        $admissionId = (int) $this->postJson($url, $this->admitPayload())->assertStatus(201)->json('data.id');

        $response = $this->postJson($url.'/'.$admissionId.'/discharge', [
            'discharge_notes' => 'Sortie autorisée, suivi en ambulatoire.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', HealthAdmission::STATUS_DISCHARGED)
            ->assertJsonPath('data.discharge_notes', 'Sortie autorisée, suivi en ambulatoire.');
        $this->assertNotNull($response->json('data.discharged_at'));

        $this->assertSame(HealthBed::STATUS_FREE, $this->bedCardio1->refresh()->status);

        // Double sortie : 422 (l'admission est déjà clôturée).
        $this->postJson($url.'/'.$admissionId.'/discharge', [])->assertStatus(422);
    }

    public function test_occupancy_rates_are_exact_per_department(): void
    {
        Sanctum::actingAs($this->receptionA);

        // 2 services, 3 lits, 1 admission (Cardiologie L1).
        $this->postJson($this->baseUrl().'/admissions', $this->admitPayload())->assertStatus(201);

        $response = $this->getJson($this->baseUrl().'/admissions/occupancy')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            // A-Cardiologie : 2 lits, 1 occupé, 1 libre → 50 %.
            ->assertJsonPath('data.0.department_id', (int) $this->cardiologyA->getAttribute('id'))
            ->assertJsonPath('data.0.total_beds', 2)
            ->assertJsonPath('data.0.occupied_beds', 1)
            ->assertJsonPath('data.0.free_beds', 1)
            // B-Chirurgie : 1 lit, 0 occupé, 1 libre → 0 %.
            ->assertJsonPath('data.1.department_id', (int) $this->surgeryA->getAttribute('id'))
            ->assertJsonPath('data.1.total_beds', 1)
            ->assertJsonPath('data.1.occupied_beds', 0)
            ->assertJsonPath('data.1.free_beds', 1)
            // Global : 3 lits, 1 occupé → 33.33 %.
            ->assertJsonPath('meta.total_beds', 3)
            ->assertJsonPath('meta.occupied_beds', 1)
            ->assertJsonPath('meta.free_beds', 2);

        // Taux exacts (comparaison numérique — JSON encode 50.0 en 50).
        $this->assertEqualsWithDelta(50.0, (float) $response->json('data.0.occupancy_rate'), 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.1.occupancy_rate'), 0.001);
        $this->assertEqualsWithDelta(33.33, (float) $response->json('meta.occupancy_rate'), 0.001);
    }

    public function test_index_filters_by_status(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl().'/admissions';

        $firstId = (int) $this->postJson($url, $this->admitPayload())->assertStatus(201)->json('data.id');
        $this->postJson($url, $this->admitPayload((int) $this->bedCardio2->getAttribute('id')))->assertStatus(201);
        $this->postJson($url.'/'.$firstId.'/discharge', [])->assertStatus(200);

        $this->getJson($url.'?status=admitted')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', HealthAdmission::STATUS_ADMITTED);
        $this->getJson($url.'?status=discharged')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $firstId);
        $this->getJson($url.'?status=bogus')->assertStatus(422);
    }

    public function test_cross_tenant_is_404_fail_closed(): void
    {
        // Fixtures du tenant B (lit + admission).
        $departmentB = $this->makeDepartment($this->companyB, 'Cardiologie B', 'CARDIO-B');
        $roomB = $this->makeRoom($departmentB, 'CARDIO-B-S1');
        $bedB = $this->makeBed($roomB, 'CARDIO-B-L1');

        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $this->companyB->id]);
        /** @var HealthPractitioner $practitionerB */
        $practitionerB = HealthPractitioner::query()->create([
            'company_id' => $this->companyB->id,
            'employee_id' => (int) $employeeB->getAttribute('id'),
            'title' => HealthPractitioner::TITLE_DR,
            'status' => HealthPractitioner::STATUS_ACTIVE,
        ]);
        /** @var HealthPatient $patientB */
        $patientB = HealthPatient::query()->create([
            'company_id' => $this->companyB->id,
            'mrn' => 'PAT-2026-0001',
            'full_name' => 'Rachid Alaoui',
            'sex' => HealthPatient::SEX_MALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);
        /** @var HealthAdmission $admissionB */
        $admissionB = HealthAdmission::query()->create([
            'company_id' => $this->companyB->id,
            'patient_id' => (int) $patientB->getAttribute('id'),
            'practitioner_id' => (int) $practitionerB->getAttribute('id'),
            'department_id' => (int) $departmentB->getAttribute('id'),
            'bed_id' => (int) $bedB->getAttribute('id'),
            'admitted_at' => now(),
            'status' => HealthAdmission::STATUS_ADMITTED,
        ]);

        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl().'/admissions';

        // Lit / patient d'un autre tenant : introuvables (fail-closed).
        $this->postJson($url, $this->admitPayload((int) $bedB->getAttribute('id')))->assertStatus(404);
        $this->postJson($url, array_merge($this->admitPayload(), [
            'patient_id' => (int) $patientB->getAttribute('id'),
        ]))->assertStatus(404);

        // Admission d'un autre tenant : 404 en lecture comme en action.
        $admissionBId = (int) $admissionB->getAttribute('id');
        $this->getJson($url.'/'.$admissionBId)->assertStatus(404);
        $this->postJson($url.'/'.$admissionBId.'/discharge', [])->assertStatus(404);
        $this->postJson($url.'/'.$admissionBId.'/transfer', [
            'bed_id' => (int) $this->bedCardio1->getAttribute('id'),
        ])->assertStatus(404);
    }
}
