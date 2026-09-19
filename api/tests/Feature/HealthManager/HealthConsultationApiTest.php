<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API consultations & ordonnances — HC-005 (#7789, BC-30).
 *
 * Couvre : 401, solution inactive 403 (fail-closed), RBAC contenu médical
 * (RÉCEPTION → 403 partout — critère d'acceptation —, employé lambda 403),
 * praticien auteur implicite + contenu chiffré au repos prouvé en base,
 * seul l'auteur (ou la direction) modifie (autre praticien → 403),
 * consultation liée à un patient du MÊME tenant uniquement (422 / 404),
 * ordonnance avec ≥ 1 ligne (422 sinon) émise par l'auteur uniquement,
 * historique des prescriptions par patient.
 */
class HealthConsultationApiTest extends TestCase
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

    private function consultationsUrl(): string
    {
        return '/api/v1/health-manager/consultations';
    }

    private function prescriptionsUrl(): string
    {
        return '/api/v1/health-manager/prescriptions';
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
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->consultationsUrl())->assertStatus(401);
        $this->postJson($this->prescriptionsUrl(), [])->assertStatus(401);
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

        $this->getJson($this->consultationsUrl())
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
        $this->getJson($this->prescriptionsUrl())->assertStatus(403);
    }

    public function test_reception_never_accesses_medical_content(): void
    {
        $consultation = $this->makeConsultation();

        // La RÉCEPTION gère l'administratif mais JAMAIS le dossier médical
        // (critère d'acceptation HC-005) — 403 sur TOUT.
        Sanctum::actingAs($this->receptionA);

        $this->getJson($this->consultationsUrl())->assertStatus(403);
        $this->postJson($this->consultationsUrl(), [])->assertStatus(403);
        $this->getJson($this->consultationsUrl().'/'.$consultation->id)->assertStatus(403);
        $this->putJson($this->consultationsUrl().'/'.$consultation->id, ['reason' => 'X'])->assertStatus(403);
        $this->getJson($this->prescriptionsUrl())->assertStatus(403);
        $this->postJson($this->prescriptionsUrl(), [])->assertStatus(403);

        // Employé lambda : 403 aussi.
        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->consultationsUrl())->assertStatus(403);
        $this->getJson($this->prescriptionsUrl())->assertStatus(403);
    }

    public function test_practitioner_documents_consultation_with_encrypted_content(): void
    {
        Sanctum::actingAs($this->doctorA);

        // L'auteur est IMPLICITE (praticien acteur) — le practitioner_id
        // envoyé est ignoré au profit du sien.
        $consultationId = (int) $this->postJson($this->consultationsUrl(), [
            'patient_id' => $this->patientA->id,
            'consulted_at' => '2026-10-05 09:30:00',
            'reason' => 'Douleur thoracique',
            'clinical_exam' => 'Auscultation normale',
            'diagnosis' => 'Angine de poitrine stable',
            'weight_kg' => 82.5,
            'height_cm' => 178,
            'blood_pressure' => '128/82',
            'temperature_c' => 37.1,
            'pulse_bpm' => 74,
            'notes' => 'Suivi dans 3 mois',
        ])->assertStatus(201)
            ->assertJsonPath('data.practitioner_id', $this->practitionerA->id)
            ->assertJsonPath('data.diagnosis', 'Angine de poitrine stable')
            ->assertJsonPath('data.pulse_bpm', 74)
            ->json('data.id');

        // Contenu médical chiffré AU REPOS : la valeur brute en base n'est
        // pas le texte en clair.
        $rawDiagnosis = (string) DB::table('health_consultations')
            ->where('id', $consultationId)->value('diagnosis');
        $this->assertNotSame('Angine de poitrine stable', $rawDiagnosis);
        $this->assertNotEmpty($rawDiagnosis);

        // Historique du patient visible par un AUTRE praticien (lecture).
        [, $otherDoctor] = $this->makePractitioner($this->companyA->id, 'Dr Karim L.');
        Sanctum::actingAs($otherDoctor);
        $this->getJson($this->consultationsUrl().'?patient_id='.$this->patientA->id)
            ->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->getJson($this->consultationsUrl().'/'.$consultationId)->assertStatus(200);
    }

    public function test_only_author_or_admin_updates_a_consultation(): void
    {
        $consultation = $this->makeConsultation();

        // Un AUTRE praticien lit mais ne modifie pas (403).
        [, $otherDoctor] = $this->makePractitioner($this->companyA->id, 'Dr Karim L.');
        Sanctum::actingAs($otherDoctor);
        $this->putJson($this->consultationsUrl().'/'.$consultation->id, ['diagnosis' => 'Autre avis'])
            ->assertStatus(403);

        // L'AUTEUR modifie.
        Sanctum::actingAs($this->doctorA);
        $this->putJson($this->consultationsUrl().'/'.$consultation->id, ['diagnosis' => 'Diagnostic affine'])
            ->assertStatus(200)->assertJsonPath('data.diagnosis', 'Diagnostic affine');

        // La direction (health.admin) modifie aussi.
        Sanctum::actingAs($this->principalA);
        $this->putJson($this->consultationsUrl().'/'.$consultation->id, ['notes' => 'Relecture direction'])
            ->assertStatus(200)->assertJsonPath('data.notes', 'Relecture direction');
    }

    public function test_consultation_is_bound_to_same_tenant_patient_only(): void
    {
        $patientB = $this->makePatient($this->companyB->id, 'PAT-2026-0001', 'Sara', 'Alami');

        Sanctum::actingAs($this->doctorA);

        // Patient d'un AUTRE tenant → 422 (Rule::exists scopée).
        $this->postJson($this->consultationsUrl(), [
            'patient_id' => $patientB->id,
            'consulted_at' => '2026-10-05 09:30:00',
            'reason' => 'Intrusion',
        ])->assertStatus(422);

        // Consultation du tenant B → 404 depuis A.
        [$practitionerB] = $this->makePractitioner($this->companyB->id, 'Dr B.');
        /** @var HealthConsultation $consultationB */
        $consultationB = HealthConsultation::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'patient_id' => $patientB->id,
            'practitioner_id' => $practitionerB->id,
            'consulted_at' => '2026-10-05 09:30:00',
            'reason' => 'Consultation B',
        ]);

        $this->getJson($this->consultationsUrl().'/'.$consultationB->id)->assertStatus(404);
        $this->putJson($this->consultationsUrl().'/'.$consultationB->id, ['reason' => 'X'])->assertStatus(404);
    }

    public function test_admin_must_designate_the_practitioner(): void
    {
        Sanctum::actingAs($this->principalA);

        // Direction non praticienne SANS practitioner_id → 422.
        $this->postJson($this->consultationsUrl(), [
            'patient_id' => $this->patientA->id,
            'consulted_at' => '2026-10-05 09:30:00',
            'reason' => 'Sans auteur',
        ])->assertStatus(422);

        // Avec un praticien ACTIF du tenant → 201.
        $this->postJson($this->consultationsUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'consulted_at' => '2026-10-05 09:30:00',
            'reason' => 'Documentee par la direction',
        ])->assertStatus(201)->assertJsonPath('data.practitioner_id', $this->practitionerA->id);
    }

    public function test_prescription_requires_at_least_one_item(): void
    {
        $consultation = $this->makeConsultation();

        Sanctum::actingAs($this->doctorA);

        // Sans ligne (ou liste vide) → 422.
        $this->postJson($this->prescriptionsUrl(), [
            'consultation_id' => $consultation->id,
        ])->assertStatus(422);
        $this->postJson($this->prescriptionsUrl(), [
            'consultation_id' => $consultation->id,
            'items' => [],
        ])->assertStatus(422);

        // Avec 2 lignes → 201, lignes persistées avec company_id serveur.
        $prescriptionId = (int) $this->postJson($this->prescriptionsUrl(), [
            'consultation_id' => $consultation->id,
            'notes' => 'A jeun le matin',
            'items' => [
                ['medication' => 'Amoxicilline 500 mg', 'dosage' => '1 g', 'frequency' => '2x/jour', 'duration' => '7 jours'],
                ['medication' => 'Paracetamol', 'dosage' => '500 mg', 'frequency' => '3x/jour', 'duration' => '5 jours', 'instructions' => 'Si douleur'],
            ],
        ])->assertStatus(201)
            ->assertJsonPath('data.patient_id', $this->patientA->id)
            ->assertJsonPath('data.practitioner_id', $this->practitionerA->id)
            ->assertJsonCount(2, 'data.items')
            ->json('data.id');

        $this->assertSame(
            2,
            DB::table('health_prescription_items')
                ->where('prescription_id', $prescriptionId)
                ->where('company_id', $this->companyA->id)
                ->count()
        );
    }

    public function test_prescription_history_per_patient_and_author_rbac(): void
    {
        $consultation = $this->makeConsultation();

        // Un AUTRE praticien ne prescrit pas sur la consultation d'un
        // confrère (403) ; l'auteur si.
        [, $otherDoctor] = $this->makePractitioner($this->companyA->id, 'Dr Karim L.');
        Sanctum::actingAs($otherDoctor);
        $this->postJson($this->prescriptionsUrl(), [
            'consultation_id' => $consultation->id,
            'items' => [['medication' => 'X', 'dosage' => '1', 'frequency' => '1x', 'duration' => '1 jour']],
        ])->assertStatus(403);

        Sanctum::actingAs($this->doctorA);
        $prescriptionId = (int) $this->postJson($this->prescriptionsUrl(), [
            'consultation_id' => $consultation->id,
            'items' => [['medication' => 'Aspirine', 'dosage' => '100 mg', 'frequency' => '1x/jour', 'duration' => '30 jours']],
        ])->assertStatus(201)->json('data.id');

        // Historique PAR PATIENT (critère HC-005), lisible par un autre
        // praticien ; patient sans ordonnance → vide.
        Sanctum::actingAs($otherDoctor);
        $this->getJson($this->prescriptionsUrl().'?patient_id='.$this->patientA->id)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $prescriptionId)
            ->assertJsonPath('data.0.items.0.medication', 'Aspirine');

        $lonely = $this->makePatient($this->companyA->id, 'PAT-2026-0002', 'Lina', 'Benali');
        $this->getJson($this->prescriptionsUrl().'?patient_id='.$lonely->id)
            ->assertStatus(200)->assertJsonPath('meta.total', 0);

        // Ordonnance d'un autre tenant → 404.
        Sanctum::actingAs($this->doctorA);
        $this->getJson($this->prescriptionsUrl().'/999999')->assertStatus(404);
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

    private function makeConsultation(): HealthConsultation
    {
        /** @var HealthConsultation $consultation */
        $consultation = HealthConsultation::query()->forceCreate([
            'company_id' => $this->companyA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'consulted_at' => '2026-10-05 09:30:00',
            'reason' => 'Consultation initiale',
        ]);

        return $consultation;
    }
}
