<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API consultations & prescriptions — HC-005 (#7789, BC-30).
 *
 * Couvre : 401, 403 solution inactive (fail-closed), 403 employé lambda,
 * 403 RÉCEPTION MÊME EN LECTURE (confidentialité médicale), praticien qui
 * crée SES consultations (practitioner_id forcé), auteur-ou-direction pour
 * la mise à jour, 404 patient cross-tenant, ordonnance ≥ 1 ligne (422),
 * historique des ordonnances par patient, chiffrement au repos.
 */
class HealthConsultationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $adminA;

    private Employee $lambdaA;

    private Employee $receptionA;

    private Employee $practitionerEmployeeA1;

    private Employee $practitionerEmployeeA2;

    private HealthPractitioner $practitionerA1;

    private HealthPractitioner $practitionerA2;

    private HealthPatient $patientA;

    private HealthPatient $patientB;

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

        /** @var Employee $receptionA */
        $receptionA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->receptionA = $receptionA;
        HealthStaffRole::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => (int) $receptionA->getAttribute('id'),
            'role' => HealthStaffRole::ROLE_RECEPTION,
        ]);

        [$this->practitionerEmployeeA1, $this->practitionerA1] = $this->makePractitioner($companyA);
        [$this->practitionerEmployeeA2, $this->practitionerA2] = $this->makePractitioner($companyA);

        $this->patientA = $this->makePatient($companyA, 'PAT-2026-0001', 'Amine Kaci');
        $this->patientB = $this->makePatient($companyB, 'PAT-2026-0001', 'Rachid Alaoui');
    }

    /**
     * @return array{0: Employee, 1: HealthPractitioner}
     */
    private function makePractitioner(Company $company): array
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var HealthPractitioner $practitioner */
        $practitioner = HealthPractitioner::query()->create([
            'company_id' => $company->id,
            'employee_id' => (int) $employee->getAttribute('id'),
            'title' => HealthPractitioner::TITLE_DR,
            'status' => HealthPractitioner::STATUS_ACTIVE,
        ]);

        return [$employee, $practitioner];
    }

    private function makePatient(Company $company, string $mrn, string $name): HealthPatient
    {
        /** @var HealthPatient $patient */
        $patient = HealthPatient::query()->create([
            'company_id' => $company->id,
            'mrn' => $mrn,
            'full_name' => $name,
            'sex' => HealthPatient::SEX_MALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);

        return $patient;
    }

    private function makeConsultation(HealthPractitioner $practitioner, HealthPatient $patient): HealthConsultation
    {
        /** @var HealthConsultation $consultation */
        $consultation = HealthConsultation::query()->create([
            'company_id' => $practitioner->company_id,
            'patient_id' => (int) $patient->getAttribute('id'),
            'practitioner_id' => (int) $practitioner->getAttribute('id'),
            'consulted_at' => now(),
            'reason' => 'Suivi',
            'diagnosis_encrypted' => 'Diagnostic confidentiel',
        ]);

        return $consultation;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/consultations')->assertStatus(401);
        $this->postJson($this->baseUrl().'/consultations', [])->assertStatus(401);
        $this->getJson($this->baseUrl().'/prescriptions')->assertStatus(401);
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

        $this->getJson($this->baseUrl().'/consultations')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
        $this->getJson($this->baseUrl().'/prescriptions')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/consultations')->assertStatus(403);
        $this->postJson($this->baseUrl().'/consultations', [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'consulted_at' => now()->toISOString(),
        ])->assertStatus(403);
        $this->getJson($this->baseUrl().'/prescriptions')->assertStatus(403);
    }

    public function test_reception_gets_403_even_on_read_medical_confidentiality(): void
    {
        $consultation = $this->makeConsultation($this->practitionerA1, $this->patientA);

        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        // Confidentialité médicale : la réception ne voit JAMAIS le contenu
        // médical, même en simple lecture (spec §2, deny-by-default).
        $this->getJson($url.'/consultations')->assertStatus(403);
        $this->getJson($url.'/consultations/'.(int) $consultation->getAttribute('id'))->assertStatus(403);
        $this->postJson($url.'/consultations', [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'consulted_at' => now()->toISOString(),
        ])->assertStatus(403);
        $this->getJson($url.'/prescriptions')->assertStatus(403);
        $this->postJson($url.'/prescriptions', [
            'consultation_id' => (int) $consultation->getAttribute('id'),
            'items' => [['medication' => 'Paracétamol 1g']],
        ])->assertStatus(403);
    }

    public function test_practitioner_creates_own_consultation_with_encrypted_vitals(): void
    {
        Sanctum::actingAs($this->practitionerEmployeeA1);

        $response = $this->postJson($this->baseUrl().'/consultations', [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            // Tentative d'usurpation : DOIT être forcé à SA fiche praticien.
            'practitioner_id' => (int) $this->practitionerA2->getAttribute('id'),
            'consulted_at' => '2026-02-10T09:30:00Z',
            'reason' => 'Douleurs thoraciques',
            'clinical_exam' => 'Auscultation normale',
            'diagnosis' => 'Angine de poitrine stable',
            'notes' => 'Contrôle dans 3 mois',
            'vitals' => [
                'weight_kg' => 82.5,
                'height_cm' => 178,
                'blood_pressure' => '13/8',
                'temperature_c' => 37.1,
                'pulse_bpm' => 74,
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.practitioner_id', (int) $this->practitionerA1->getAttribute('id'))
            ->assertJsonPath('data.patient_id', (int) $this->patientA->getAttribute('id'))
            ->assertJsonPath('data.diagnosis', 'Angine de poitrine stable')
            ->assertJsonPath('data.vitals.blood_pressure', '13/8')
            ->assertJsonPath('data.vitals.pulse_bpm', 74);

        // Chiffrement au repos : le diagnostic n'est jamais stocké en clair.
        /** @var string $rawDiagnosis */
        $rawDiagnosis = DB::table('health_consultations')
            ->where('id', (int) $response->json('data.id'))
            ->value('diagnosis_encrypted');
        $this->assertNotSame('Angine de poitrine stable', $rawDiagnosis);
    }

    public function test_vitals_must_be_structured(): void
    {
        Sanctum::actingAs($this->practitionerEmployeeA1);

        $this->postJson($this->baseUrl().'/consultations', [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'consulted_at' => now()->toISOString(),
            'vitals' => [
                'weight_kg' => 'lourd',
                'pulse_bpm' => 'rapide',
                'unknown_key' => 'x',
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['vitals', 'vitals.weight_kg', 'vitals.pulse_bpm']);
    }

    public function test_other_practitioner_cannot_update_but_author_and_admin_can(): void
    {
        $consultation = $this->makeConsultation($this->practitionerA1, $this->patientA);
        $url = $this->baseUrl().'/consultations/'.(int) $consultation->getAttribute('id');

        // Un AUTRE praticien du tenant : lecture oui, mise à jour NON.
        Sanctum::actingAs($this->practitionerEmployeeA2);
        $this->getJson($url)->assertStatus(200);
        $this->putJson($url, ['diagnosis' => 'Tentative de réécriture'])->assertStatus(403);

        // L'auteur peut corriger sa consultation.
        Sanctum::actingAs($this->practitionerEmployeeA1);
        $this->putJson($url, ['diagnosis' => 'Diagnostic affiné'])
            ->assertStatus(200)
            ->assertJsonPath('data.diagnosis', 'Diagnostic affiné');

        // La direction aussi.
        Sanctum::actingAs($this->adminA);
        $this->putJson($url, ['notes' => 'Relu par la direction'])
            ->assertStatus(200)
            ->assertJsonPath('data.notes', 'Relu par la direction');
    }

    public function test_cross_tenant_patient_gets_404(): void
    {
        Sanctum::actingAs($this->adminA);

        $this->postJson($this->baseUrl().'/consultations', [
            'patient_id' => (int) $this->patientB->getAttribute('id'),
            'practitioner_id' => (int) $this->practitionerA1->getAttribute('id'),
            'consulted_at' => now()->toISOString(),
        ])->assertStatus(404);

        // Consultation d'un autre tenant : introuvable (fail-closed).
        [, $practitionerB] = $this->makePractitioner($this->companyB);
        $foreign = $this->makeConsultation($practitionerB, $this->patientB);
        $this->getJson($this->baseUrl().'/consultations/'.(int) $foreign->getAttribute('id'))
            ->assertStatus(404);
    }

    public function test_index_filters_by_patient_and_practitioner(): void
    {
        $mine = $this->makeConsultation($this->practitionerA1, $this->patientA);
        $other = $this->makeConsultation($this->practitionerA2, $this->patientA);

        Sanctum::actingAs($this->adminA);
        $url = $this->baseUrl().'/consultations';

        $this->getJson($url.'?patient_id='.(int) $this->patientA->getAttribute('id'))
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $byPractitioner = $this->getJson($url.'?practitioner_id='.(int) $this->practitionerA1->getAttribute('id'))
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
        $this->assertSame((int) $mine->getAttribute('id'), $byPractitioner->json('data.0.id'));
        $this->assertNotSame((int) $other->getAttribute('id'), $byPractitioner->json('data.0.id'));
    }

    public function test_prescription_requires_at_least_one_item(): void
    {
        $consultation = $this->makeConsultation($this->practitionerA1, $this->patientA);

        Sanctum::actingAs($this->practitionerEmployeeA1);
        $url = $this->baseUrl().'/prescriptions';
        $consultationId = (int) $consultation->getAttribute('id');

        $this->postJson($url, ['consultation_id' => $consultationId])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
        $this->postJson($url, ['consultation_id' => $consultationId, 'items' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
        $this->postJson($url, ['consultation_id' => $consultationId, 'items' => [['dosage' => '1g']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.medication']);
    }

    public function test_prescription_store_and_history_by_patient(): void
    {
        $consultationA1 = $this->makeConsultation($this->practitionerA1, $this->patientA);
        $otherPatient = $this->makePatient($this->companyA, 'PAT-2026-0002', 'Yasmina Brahimi');
        $consultationA2 = $this->makeConsultation($this->practitionerA2, $otherPatient);

        // Le praticien auteur prescrit sur SA consultation (2 lignes).
        Sanctum::actingAs($this->practitionerEmployeeA1);
        $url = $this->baseUrl().'/prescriptions';

        $created = $this->postJson($url, [
            'consultation_id' => (int) $consultationA1->getAttribute('id'),
            'notes' => 'À jeun le matin',
            'items' => [
                ['medication' => 'Aspirine 100mg', 'dosage' => '1 comprimé', 'frequency' => '1x/jour', 'duration' => '30 jours'],
                ['medication' => 'Atorvastatine 20mg', 'dosage' => '1 comprimé', 'frequency' => 'le soir', 'instructions' => 'Après le repas'],
            ],
        ])->assertStatus(201)
            // Patient et praticien DÉRIVÉS de la consultation.
            ->assertJsonPath('data.patient_id', (int) $this->patientA->getAttribute('id'))
            ->assertJsonPath('data.practitioner_id', (int) $this->practitionerA1->getAttribute('id'))
            ->assertJsonPath('data.items.0.medication', 'Aspirine 100mg')
            ->assertJsonPath('data.items.1.medication', 'Atorvastatine 20mg')
            ->assertJsonCount(2, 'data.items');

        // Ordonnance d'un autre praticien pour un autre patient.
        Sanctum::actingAs($this->practitionerEmployeeA2);
        $this->postJson($url, [
            'consultation_id' => (int) $consultationA2->getAttribute('id'),
            'items' => [['medication' => 'Amoxicilline 500mg']],
        ])->assertStatus(201);

        // Historique par patient (direction) : uniquement SES ordonnances.
        Sanctum::actingAs($this->adminA);
        $history = $this->getJson($url.'?patient_id='.(int) $this->patientA->getAttribute('id'))
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
        $this->assertSame((int) $created->json('data.id'), $history->json('data.0.id'));

        $this->getJson($url.'/'.(int) $created->json('data.id'))
            ->assertStatus(200)
            ->assertJsonPath('data.notes', 'À jeun le matin')
            ->assertJsonCount(2, 'data.items');
    }
}
