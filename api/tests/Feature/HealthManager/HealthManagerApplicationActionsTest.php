<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Application\Actions\AdmitHealthPatientAction;
use App\Modules\HealthManager\Application\Actions\ArchiveHealthPatientAction;
use App\Modules\HealthManager\Application\Actions\CreateHealthPrescriptionAction;
use App\Modules\HealthManager\Application\Actions\DeleteHealthPractitionerAction;
use App\Modules\HealthManager\Application\Actions\DischargeHealthAdmissionAction;
use App\Modules\HealthManager\Application\Actions\RecordHealthConsultationAction;
use App\Modules\HealthManager\Application\Actions\RegisterHealthPatientAction;
use App\Modules\HealthManager\Application\Actions\RegisterHealthPractitionerAction;
use App\Modules\HealthManager\Application\Actions\ScheduleHealthAppointmentAction;
use App\Modules\HealthManager\Application\Actions\TransferHealthAdmissionAction;
use App\Modules\HealthManager\Application\Actions\TransitionHealthAppointmentStatusAction;
use App\Modules\HealthManager\Application\Actions\UpdateHealthAppointmentAction;
use App\Modules\HealthManager\Application\Actions\UpdateHealthConsultationAction;
use App\Modules\HealthManager\Application\Actions\UpdateHealthPatientAction;
use App\Modules\HealthManager\Application\Actions\UpdateHealthPractitionerAction;
use App\Modules\HealthManager\Domain\Exceptions\HealthAppointmentConflictException;
use App\Modules\HealthManager\Domain\Exceptions\HealthInvalidTransitionException;
use App\Modules\HealthManager\Domain\Exceptions\HealthResourceInUseException;
use App\Modules\HealthManager\Domain\Models\HealthAdmission;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Domain\Models\HealthBed;
use App\Modules\HealthManager\Domain\Models\HealthConsultation;
use App\Modules\HealthManager\Domain\Models\HealthDepartment;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthPractitionerSpecialty;
use App\Modules\HealthManager\Domain\Models\HealthRoom;
use App\Modules\HealthManager\Domain\Models\HealthSpecialty;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Tests des Actions de la couche Application HealthManager — BOS-024b
 * (#8213, BC-31).
 *
 * Chaque Action est éprouvée directement (conteneur), sur ses
 * responsabilités propres : orchestration, invariants (409/422 portés par
 * les services et exceptions Domain), dérivations serveur (MRN, statut,
 * patient/praticien dérivés de la consultation), mapping vers les colonnes
 * chiffrées, transactions (admission ↔ lit, ordonnance ↔ lignes, pivot
 * spécialités) et comportement fail-closed cross-tenant (404).
 * La parité HTTP de bout en bout reste couverte par les suites de contrat
 * existantes (HealthAppointmentTest, HealthAdmissionTest…), vertes sans
 * modification — preuve d'absence de changement d'API.
 */
class HealthManagerApplicationActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $adminA;

    private Employee $lambdaA;

    private Employee $practitionerEmployeeA;

    private HealthPractitioner $practitionerA;

    private HealthPatient $patientA;

    private HealthBed $bedA1;

    private HealthBed $bedA2;

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

        /** @var Employee $practitionerEmployeeA */
        $practitionerEmployeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->practitionerEmployeeA = $practitionerEmployeeA;
        $this->practitionerA = $this->makePractitioner($companyA, $practitionerEmployeeA);

        $this->patientA = $this->makePatient($companyA);

        $department = $this->makeDepartment($companyA, 'Cardiologie', 'CARDIO');
        $room = $this->makeRoom($department, 'CARDIO-S1');
        $this->bedA1 = $this->makeBed($room, 'CARDIO-L1');
        $this->bedA2 = $this->makeBed($room, 'CARDIO-L2');
    }

    // ── Fixtures ────────────────────────────────────────────────────────

    private function makePractitioner(Company $company, Employee $employee): HealthPractitioner
    {
        /** @var HealthPractitioner $practitioner */
        $practitioner = HealthPractitioner::query()->create([
            'company_id' => $company->id,
            'employee_id' => (int) $employee->getAttribute('id'),
            'title' => HealthPractitioner::TITLE_DR,
            'status' => HealthPractitioner::STATUS_ACTIVE,
        ]);

        return $practitioner;
    }

    private function makePatient(Company $company): HealthPatient
    {
        /** @var HealthPatient $patient */
        $patient = HealthPatient::query()->create([
            'company_id' => $company->id,
            'mrn' => 'PAT-'.now()->format('Y').'-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'full_name' => 'Patient Test',
            'sex' => HealthPatient::SEX_MALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);

        return $patient;
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

    private function makeConsultation(HealthPractitioner $practitioner, HealthPatient $patient): HealthConsultation
    {
        /** @var HealthConsultation $consultation */
        $consultation = HealthConsultation::query()->create([
            'company_id' => $practitioner->company_id,
            'patient_id' => (int) $patient->getAttribute('id'),
            'practitioner_id' => (int) $practitioner->getAttribute('id'),
            'consulted_at' => now(),
            'reason' => 'Suivi',
        ]);

        return $consultation;
    }

    /**
     * @return array<string, mixed>
     */
    private function slotPayload(string $startsAt, string $endsAt): array
    {
        return [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'practitioner_id' => (int) $this->practitionerA->getAttribute('id'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => 'Consultation de contrôle',
        ];
    }

    // ── Rendez-vous ─────────────────────────────────────────────────────

    public function test_schedule_action_creates_scheduled_appointment(): void
    {
        $action = app(ScheduleHealthAppointmentAction::class);

        $appointment = $action->execute(
            (string) $this->companyA->id,
            $this->slotPayload('2026-10-01T09:00:00Z', '2026-10-01T09:30:00Z'),
        );

        $this->assertSame(HealthAppointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertSame((string) $this->companyA->id, (string) $appointment->company_id);
        $this->assertDatabaseHas('health_appointments', [
            'id' => $appointment->id,
            'status' => HealthAppointment::STATUS_SCHEDULED,
        ]);
    }

    public function test_schedule_action_rejects_practitioner_conflict(): void
    {
        $action = app(ScheduleHealthAppointmentAction::class);
        $action->execute((string) $this->companyA->id, $this->slotPayload('2026-10-01T09:00:00Z', '2026-10-01T09:30:00Z'));

        $this->expectException(HealthAppointmentConflictException::class);
        $action->execute((string) $this->companyA->id, $this->slotPayload('2026-10-01T09:15:00Z', '2026-10-01T09:45:00Z'));
    }

    public function test_update_action_moves_slot_and_ignores_self_on_conflict(): void
    {
        $schedule = app(ScheduleHealthAppointmentAction::class);
        $appointment = $schedule->execute(
            (string) $this->companyA->id,
            $this->slotPayload('2026-10-01T09:00:00Z', '2026-10-01T09:30:00Z'),
        );

        $update = app(UpdateHealthAppointmentAction::class);
        $updated = $update->execute($appointment, [
            'starts_at' => '2026-10-01T10:00:00Z',
            'ends_at' => '2026-10-01T10:30:00Z',
        ]);

        $this->assertSame('2026-10-01T10:00:00+00:00', $updated->starts_at->toIso8601String());
    }

    public function test_update_action_rejects_invalid_time_range_on_merged_state(): void
    {
        $schedule = app(ScheduleHealthAppointmentAction::class);
        $appointment = $schedule->execute(
            (string) $this->companyA->id,
            $this->slotPayload('2026-10-01T09:00:00Z', '2026-10-01T09:30:00Z'),
        );

        // ends_at seul, reculé AVANT le starts_at conservé → 422 (état fusionné).
        $this->expectException(HttpException::class);
        app(UpdateHealthAppointmentAction::class)->execute($appointment, [
            'ends_at' => '2026-10-01T08:00:00Z',
        ]);
    }

    public function test_update_action_rejects_conflict_with_other_slot(): void
    {
        $schedule = app(ScheduleHealthAppointmentAction::class);
        $schedule->execute((string) $this->companyA->id, $this->slotPayload('2026-10-01T09:00:00Z', '2026-10-01T09:30:00Z'));
        $second = $schedule->execute((string) $this->companyA->id, $this->slotPayload('2026-10-01T11:00:00Z', '2026-10-01T11:30:00Z'));

        $this->expectException(HealthAppointmentConflictException::class);
        app(UpdateHealthAppointmentAction::class)->execute($second, [
            'starts_at' => '2026-10-01T09:15:00Z',
        ]);
    }

    public function test_transition_action_applies_valid_transition(): void
    {
        $appointment = app(ScheduleHealthAppointmentAction::class)->execute(
            (string) $this->companyA->id,
            $this->slotPayload('2026-10-01T09:00:00Z', '2026-10-01T09:30:00Z'),
        );

        $transitioned = app(TransitionHealthAppointmentStatusAction::class)
            ->execute($appointment, HealthAppointment::STATUS_CONFIRMED);

        $this->assertSame(HealthAppointment::STATUS_CONFIRMED, $transitioned->status);
    }

    public function test_transition_action_rejects_invalid_transition(): void
    {
        $appointment = app(ScheduleHealthAppointmentAction::class)->execute(
            (string) $this->companyA->id,
            $this->slotPayload('2026-10-01T09:00:00Z', '2026-10-01T09:30:00Z'),
        );

        $this->expectException(HealthInvalidTransitionException::class);
        app(TransitionHealthAppointmentStatusAction::class)
            ->execute($appointment, HealthAppointment::STATUS_COMPLETED);
    }

    // ── Admissions ──────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function admitPayload(?int $bedId = null): array
    {
        return [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'practitioner_id' => (int) $this->practitionerA->getAttribute('id'),
            'bed_id' => $bedId ?? (int) $this->bedA1->getAttribute('id'),
            'reason' => 'Surveillance post-opératoire',
        ];
    }

    public function test_admit_action_creates_admission_and_occupies_bed(): void
    {
        $admission = app(AdmitHealthPatientAction::class)
            ->execute((string) $this->companyA->id, $this->admitPayload());

        $this->assertSame(HealthAdmission::STATUS_ADMITTED, $admission->status);
        $this->assertSame(
            HealthBed::STATUS_OCCUPIED,
            $this->bedA1->refresh()->status,
        );
    }

    public function test_admit_action_rejects_patient_of_another_tenant(): void
    {
        $patientB = $this->makePatient($this->companyB);

        $this->expectException(ModelNotFoundException::class);
        app(AdmitHealthPatientAction::class)->execute((string) $this->companyA->id, [
            'patient_id' => (int) $patientB->getAttribute('id'),
            'practitioner_id' => (int) $this->practitionerA->getAttribute('id'),
            'bed_id' => (int) $this->bedA1->getAttribute('id'),
        ]);
    }

    public function test_transfer_action_frees_old_bed_and_occupies_new(): void
    {
        $admission = app(AdmitHealthPatientAction::class)
            ->execute((string) $this->companyA->id, $this->admitPayload());

        $transferred = app(TransferHealthAdmissionAction::class)
            ->execute($admission, (int) $this->bedA2->getAttribute('id'));

        $this->assertSame(HealthAdmission::STATUS_TRANSFERRED, $transferred->status);
        $this->assertSame((int) $this->bedA2->getAttribute('id'), $transferred->bed_id);
        $this->assertSame(HealthBed::STATUS_FREE, $this->bedA1->refresh()->status);
        $this->assertSame(HealthBed::STATUS_OCCUPIED, $this->bedA2->refresh()->status);
    }

    public function test_discharge_action_marks_discharged_and_frees_bed(): void
    {
        $admission = app(AdmitHealthPatientAction::class)
            ->execute((string) $this->companyA->id, $this->admitPayload());

        $discharged = app(DischargeHealthAdmissionAction::class)
            ->execute($admission, 'Sortie autorisée');

        $this->assertSame(HealthAdmission::STATUS_DISCHARGED, $discharged->status);
        $this->assertNotNull($discharged->discharged_at);
        $this->assertSame('Sortie autorisée', $discharged->discharge_notes);
        $this->assertSame(HealthBed::STATUS_FREE, $this->bedA1->refresh()->status);
    }

    public function test_discharge_action_rejects_double_discharge(): void
    {
        $admission = app(AdmitHealthPatientAction::class)
            ->execute((string) $this->companyA->id, $this->admitPayload());
        $discharge = app(DischargeHealthAdmissionAction::class);
        $discharge->execute($admission, null);

        $this->expectException(ValidationException::class);
        $discharge->execute($admission->refresh(), null);
    }

    // ── Patients ────────────────────────────────────────────────────────

    public function test_register_action_creates_active_patient_with_mrn_and_encrypted_columns(): void
    {
        $patient = app(RegisterHealthPatientAction::class)->execute((string) $this->companyA->id, [
            'full_name' => 'Nadia Benali',
            'sex' => HealthPatient::SEX_FEMALE,
            'phone' => '+213555123456',
            'email' => 'nadia@example.test',
        ]);

        $this->assertSame(HealthPatient::STATUS_ACTIVE, $patient->status);
        $this->assertMatchesRegularExpression(
            '/^PAT-'.now()->format('Y').'-\d{4}$/',
            (string) $patient->mrn,
        );
        // Clairs API mappés vers les colonnes chiffrées (round-trip du cast).
        $this->assertSame('+213555123456', $patient->phone_encrypted);
        $this->assertSame('nadia@example.test', $patient->email_encrypted);
    }

    public function test_update_action_maps_encrypted_inputs(): void
    {
        $updated = app(UpdateHealthPatientAction::class)->execute($this->patientA, [
            'phone' => '+213555999999',
            'full_name' => 'Patient Renommé',
        ]);

        $this->assertSame('+213555999999', $updated->phone_encrypted);
        $this->assertSame('Patient Renommé', $updated->full_name);
    }

    public function test_archive_action_sets_archived_status_without_deleting(): void
    {
        $archived = app(ArchiveHealthPatientAction::class)->execute($this->patientA);

        $this->assertSame(HealthPatient::STATUS_ARCHIVED, $archived->status);
        $this->assertDatabaseHas('health_patients', [
            'id' => (int) $this->patientA->getAttribute('id'),
            'status' => HealthPatient::STATUS_ARCHIVED,
        ]);
    }

    // ── Consultations ───────────────────────────────────────────────────

    public function test_record_action_forces_own_practitioner_for_non_admin(): void
    {
        $consultation = app(RecordHealthConsultationAction::class)->execute($this->practitionerEmployeeA, [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'consulted_at' => '2026-10-01T10:00:00Z',
            'reason' => 'Fièvre',
            'diagnosis' => 'Virose saisonnière',
        ]);

        $this->assertSame(
            (int) $this->practitionerA->getAttribute('id'),
            $consultation->practitioner_id,
        );
        $this->assertSame('Virose saisonnière', $consultation->diagnosis_encrypted);
        $this->assertSame((string) $this->companyA->id, (string) $consultation->company_id);
    }

    public function test_record_action_allows_admin_to_designate_practitioner(): void
    {
        $consultation = app(RecordHealthConsultationAction::class)->execute($this->adminA, [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'practitioner_id' => (int) $this->practitionerA->getAttribute('id'),
            'consulted_at' => '2026-10-01T10:00:00Z',
        ]);

        $this->assertSame(
            (int) $this->practitionerA->getAttribute('id'),
            $consultation->practitioner_id,
        );
    }

    public function test_record_action_rejects_patient_of_another_tenant(): void
    {
        $patientB = $this->makePatient($this->companyB);

        $this->expectException(ModelNotFoundException::class);
        app(RecordHealthConsultationAction::class)->execute($this->practitionerEmployeeA, [
            'patient_id' => (int) $patientB->getAttribute('id'),
            'consulted_at' => '2026-10-01T10:00:00Z',
        ]);
    }

    public function test_record_action_requires_practitioner_when_actor_has_no_fiche(): void
    {
        $this->expectException(ValidationException::class);
        app(RecordHealthConsultationAction::class)->execute($this->lambdaA, [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'consulted_at' => '2026-10-01T10:00:00Z',
        ]);
    }

    public function test_update_action_maps_medical_attributes(): void
    {
        $consultation = $this->makeConsultation($this->practitionerA, $this->patientA);

        $updated = app(UpdateHealthConsultationAction::class)->execute($consultation, [
            'diagnosis' => 'Diagnostic révisé',
            'reason' => 'Suivi renforcé',
        ]);

        $this->assertSame('Diagnostic révisé', $updated->diagnosis_encrypted);
        $this->assertSame('Suivi renforcé', $updated->reason);
    }

    // ── Prescriptions ───────────────────────────────────────────────────

    public function test_create_action_derives_patient_and_practitioner_with_items(): void
    {
        $consultation = $this->makeConsultation($this->practitionerA, $this->patientA);

        $prescription = app(CreateHealthPrescriptionAction::class)->execute($this->practitionerEmployeeA, [
            'consultation_id' => (int) $consultation->getAttribute('id'),
            'items' => [
                ['medication' => 'Paracétamol', 'dosage' => '500mg', 'frequency' => '3/j'],
                ['medication' => 'Ibuprofène'],
            ],
        ]);

        $this->assertSame((int) $this->patientA->getAttribute('id'), $prescription->patient_id);
        $this->assertSame(
            (int) $this->practitionerA->getAttribute('id'),
            $prescription->practitioner_id,
        );
        $this->assertCount(2, $prescription->items);
        $this->assertDatabaseHas('health_prescription_items', [
            'prescription_id' => (int) $prescription->getAttribute('id'),
            'medication' => 'Paracétamol',
            'company_id' => (string) $this->companyA->id,
        ]);
    }

    public function test_create_action_rejects_other_practitioners_consultation(): void
    {
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $otherPractitioner = $this->makePractitioner($this->companyA, $otherEmployee);
        $consultation = $this->makeConsultation($otherPractitioner, $this->patientA);

        $this->expectException(ValidationException::class);
        app(CreateHealthPrescriptionAction::class)->execute($this->practitionerEmployeeA, [
            'consultation_id' => (int) $consultation->getAttribute('id'),
            'items' => [['medication' => 'Paracétamol']],
        ]);
    }

    // ── Praticiens ──────────────────────────────────────────────────────

    public function test_register_action_creates_practitioner_and_syncs_specialties(): void
    {
        /** @var HealthSpecialty $cardiology */
        $cardiology = HealthSpecialty::query()->create([
            'company_id' => $this->companyA->id,
            'name' => 'Cardiologie',
            'code' => 'CARD',
        ]);
        /** @var HealthSpecialty $neurology */
        $neurology = HealthSpecialty::query()->create([
            'company_id' => $this->companyA->id,
            'name' => 'Neurologie',
            'code' => 'NEURO',
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);

        $practitioner = app(RegisterHealthPractitionerAction::class)->execute((string) $this->companyA->id, [
            'employee_id' => (int) $employee->getAttribute('id'),
            'title' => HealthPractitioner::TITLE_DR,
            'status' => HealthPractitioner::STATUS_ACTIVE,
            'specialty_ids' => [
                (int) $cardiology->getAttribute('id'),
                (int) $neurology->getAttribute('id'),
            ],
        ]);

        $specialtyIds = HealthPractitionerSpecialty::query()
            ->where('company_id', (string) $this->companyA->id)
            ->where('practitioner_id', (int) $practitioner->getAttribute('id'))
            ->pluck('specialty_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            [(int) $cardiology->getAttribute('id'), (int) $neurology->getAttribute('id')],
            $specialtyIds,
        );
    }

    public function test_update_action_syncs_specialties_only_when_key_present(): void
    {
        /** @var HealthSpecialty $cardiology */
        $cardiology = HealthSpecialty::query()->create([
            'company_id' => $this->companyA->id,
            'name' => 'Cardiologie',
            'code' => 'CARD',
        ]);

        $update = app(UpdateHealthPractitionerAction::class);

        // Sans la clé specialty_ids : pivot intact.
        $update->execute($this->practitionerA, (string) $this->companyA->id, [
            'license_number' => 'LIC-001',
        ]);
        $this->assertSame(0, HealthPractitionerSpecialty::query()
            ->where('practitioner_id', (int) $this->practitionerA->getAttribute('id'))
            ->count());

        // Avec la clé : synchronisation (ajout).
        $updated = $update->execute($this->practitionerA, (string) $this->companyA->id, [
            'specialty_ids' => [(int) $cardiology->getAttribute('id')],
        ]);

        $this->assertSame(
            [(int) $cardiology->getAttribute('id')],
            $updated->practitionerSpecialties()
                ->pluck('specialty_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all(),
        );
    }

    public function test_delete_action_blocks_practitioner_in_use(): void
    {
        app(ScheduleHealthAppointmentAction::class)->execute(
            (string) $this->companyA->id,
            $this->slotPayload('2026-10-01T09:00:00Z', '2026-10-01T09:30:00Z'),
        );

        $this->expectException(HealthResourceInUseException::class);
        app(DeleteHealthPractitionerAction::class)->execute($this->practitionerA);
    }

    public function test_delete_action_removes_practitioner_and_pivot(): void
    {
        /** @var HealthSpecialty $cardiology */
        $cardiology = HealthSpecialty::query()->create([
            'company_id' => $this->companyA->id,
            'name' => 'Cardiologie',
            'code' => 'CARD',
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $practitioner = app(RegisterHealthPractitionerAction::class)->execute((string) $this->companyA->id, [
            'employee_id' => (int) $employee->getAttribute('id'),
            'title' => HealthPractitioner::TITLE_DR,
            'status' => HealthPractitioner::STATUS_ACTIVE,
            'specialty_ids' => [(int) $cardiology->getAttribute('id')],
        ]);

        app(DeleteHealthPractitionerAction::class)->execute($practitioner);

        $this->assertDatabaseMissing('health_practitioners', [
            'id' => (int) $practitioner->getAttribute('id'),
        ]);
        $this->assertSame(0, HealthPractitionerSpecialty::query()
            ->where('practitioner_id', (int) $practitioner->getAttribute('id'))
            ->count());
    }
}
