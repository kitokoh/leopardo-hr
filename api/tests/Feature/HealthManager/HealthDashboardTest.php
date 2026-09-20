<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use App\Modules\HealthManager\Domain\Models\HealthInvoice;
use App\Modules\HealthManager\Domain\Models\HealthInvoicePayment;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API tableau de bord clinique — HC-008 (#7792, BC-30).
 *
 * Couvre : matrice 401 / 403 flag inactif / 403 lambda ; agrégats corrects
 * (1 rendez-vous du jour, 1 admission avec lit occupé, 1 encaissement du
 * mois, patients récents) — contrat champ-à-champ du type `HealthDashboard`
 * de `front/web/src/lib/health-api.ts` ; accès praticien.
 */
class HealthDashboardTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $adminA;

    private Employee $practitionerEmployeeA;

    private Employee $lambdaA;

    private HealthPatient $patientA;

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

        /** @var Employee $adminA */
        $adminA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->adminA = $adminA;

        /** @var Employee $practitionerEmployeeA */
        $practitionerEmployeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->practitionerEmployeeA = $practitionerEmployeeA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        // Structure : service → salle → 2 lits (1 occupé, 1 libre).
        /** @var HealthDepartment $department */
        $department = HealthDepartment::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Médecine interne',
            'code' => 'MED-INT',
            'status' => 'active',
        ]);

        /** @var HealthRoom $room */
        $room = HealthRoom::query()->create([
            'company_id' => $companyA->id,
            'department_id' => $department->getAttribute('id'),
            'name' => 'Salle 101',
            'code' => 'S-101',
            'type' => 'hospitalization',
            'status' => 'active',
        ]);

        /** @var HealthBed $occupiedBed */
        $occupiedBed = HealthBed::query()->create([
            'company_id' => $companyA->id,
            'room_id' => $room->getAttribute('id'),
            'code' => 'B-101-1',
            'status' => HealthBed::STATUS_OCCUPIED,
        ]);

        HealthBed::query()->create([
            'company_id' => $companyA->id,
            'room_id' => $room->getAttribute('id'),
            'code' => 'B-101-2',
            'status' => HealthBed::STATUS_FREE,
        ]);

        /** @var HealthPractitioner $practitioner */
        $practitioner = HealthPractitioner::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $practitionerEmployeeA->id,
            'department_id' => $department->getAttribute('id'),
            'title' => 'dr',
            'status' => 'active',
        ]);

        /** @var HealthPatient $patientA */
        $patientA = HealthPatient::query()->create([
            'company_id' => $companyA->id,
            'mrn' => 'PAT-2026-0001',
            'full_name' => 'Amine Kaci',
            'sex' => HealthPatient::SEX_MALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);
        $this->patientA = $patientA;

        // 1 rendez-vous AUJOURD'HUI (+ 1 annulé, exclu du compteur).
        HealthAppointment::query()->create([
            'company_id' => $companyA->id,
            'patient_id' => $patientA->getAttribute('id'),
            'practitioner_id' => $practitioner->getAttribute('id'),
            'starts_at' => now()->setTime(10, 0),
            'ends_at' => now()->setTime(10, 30),
            'status' => HealthAppointment::STATUS_SCHEDULED,
        ]);

        HealthAppointment::query()->create([
            'company_id' => $companyA->id,
            'patient_id' => $patientA->getAttribute('id'),
            'practitioner_id' => $practitioner->getAttribute('id'),
            'starts_at' => now()->setTime(11, 0),
            'ends_at' => now()->setTime(11, 30),
            'status' => HealthAppointment::STATUS_CANCELLED,
        ]);

        // 1 admission active sur le lit occupé.
        HealthAdmission::query()->create([
            'company_id' => $companyA->id,
            'patient_id' => $patientA->getAttribute('id'),
            'practitioner_id' => $practitioner->getAttribute('id'),
            'department_id' => $department->getAttribute('id'),
            'bed_id' => $occupiedBed->getAttribute('id'),
            'admitted_at' => now()->subDay(),
            'status' => HealthAdmission::STATUS_ADMITTED,
        ]);

        // 1 encaissement du mois courant (150.00).
        /** @var HealthInvoice $invoice */
        $invoice = HealthInvoice::query()->create([
            'company_id' => $companyA->id,
            'number' => 'HINV-2026-0001',
            'patient_id' => $patientA->getAttribute('id'),
            'status' => HealthInvoice::STATUS_PAID,
            'currency' => 'DZD',
            'subtotal' => '150.00',
            'discount' => '0.00',
            'total' => '150.00',
            'amount_paid' => '150.00',
            'issued_at' => now(),
        ]);

        HealthInvoicePayment::query()->create([
            'company_id' => $companyA->id,
            'invoice_id' => $invoice->getAttribute('id'),
            'amount' => '150.00',
            'method' => HealthInvoicePayment::METHOD_CASH,
            'paid_at' => now()->startOfMonth()->addDay(),
        ]);
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/dashboard')->assertStatus(401);
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

        $this->getJson($this->baseUrl().'/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/dashboard')->assertStatus(403);
    }

    public function test_dashboard_aggregates_for_admin(): void
    {
        Sanctum::actingAs($this->adminA);

        $response = $this->getJson($this->baseUrl().'/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.admissions_active', 1)
            ->assertJsonPath('data.occupancy.total_beds', 2)
            ->assertJsonPath('data.occupancy.occupied_beds', 1)
            ->assertJsonPath('data.occupancy.free_beds', 1)
            ->assertJsonPath('data.occupancy.maintenance_beds', 0)
            ->assertJsonPath('data.occupancy.occupancy_rate', 0.5)
            ->assertJsonPath('data.occupancy.by_department.0.department_name', 'Médecine interne')
            ->assertJsonPath('data.occupancy.by_department.0.total', 2)
            ->assertJsonPath('data.occupancy.by_department.0.occupied', 1)
            ->assertJsonPath('data.currency', 'DZD')
            ->assertJsonCount(1, 'data.recent_patients')
            ->assertJsonPath('data.recent_patients.0.id', $this->patientA->getAttribute('id'))
            ->assertJsonPath('data.recent_patients.0.mrn', 'PAT-2026-0001')
            ->assertJsonPath('data.recent_patients.0.full_name', 'Amine Kaci');

        $this->assertSame(1, $response->json('data.appointments_today'));
        $this->assertSame(1, $response->json('data.patients_count'));
        $this->assertEquals(150, $response->json('data.month_revenue'));
    }

    public function test_recent_patients_are_capped_at_five_most_recent(): void
    {
        foreach (range(2, 7) as $i) {
            HealthPatient::query()->create([
                'company_id' => $this->companyA->id,
                'mrn' => sprintf('PAT-2026-%04d', $i),
                'full_name' => 'Patient '.$i,
                'sex' => HealthPatient::SEX_OTHER,
                'status' => HealthPatient::STATUS_ACTIVE,
            ]);
        }

        Sanctum::actingAs($this->adminA);

        $this->getJson($this->baseUrl().'/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.patients_count', 7)
            ->assertJsonCount(5, 'data.recent_patients')
            ->assertJsonPath('data.recent_patients.0.mrn', 'PAT-2026-0007');
    }

    public function test_practitioner_can_view_dashboard(): void
    {
        Sanctum::actingAs($this->practitionerEmployeeA);

        $this->getJson($this->baseUrl().'/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.appointments_today', 1);
    }
}
