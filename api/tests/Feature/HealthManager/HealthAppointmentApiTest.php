<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API rendez-vous & agenda praticiens — HC-004 (#7788, BC-30).
 *
 * Couvre : 401, solution inactive 403 (fail-closed), employé lambda 403,
 * planification par l'accueil, chevauchement praticien refusé (409) et
 * créneau libéré par une annulation, transitions de statut validées (422
 * sur transition invalide, chemin nominal complet), praticien borné à SON
 * agenda (index + /agenda + 403 sur l'agenda d'un autre), replanification
 * re-vérifiée (409), isolation cross-tenant (404, 422).
 */
class HealthAppointmentApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $receptionA;

    private Employee $lambdaA;

    private HealthPatient $patientA;

    private HealthPractitioner $practitionerA;

    private function baseUrl(): string
    {
        return '/api/v1/health-manager/appointments';
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
        $this->practitionerA = $this->makePractitioner($companyA->id, 'Dr Sarah B.');
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl())->assertStatus(401);
        $this->postJson($this->baseUrl(), [])->assertStatus(401);
        $this->getJson($this->baseUrl().'/agenda')->assertStatus(401);
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
        $this->getJson($this->baseUrl().'/agenda')->assertStatus(403);
    }

    public function test_plain_employee_gets_403_on_everything(): void
    {
        $appointment = $this->makeAppointment('2026-10-05 09:00:00', '2026-10-05 09:30:00');

        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl())->assertStatus(403);
        $this->getJson($this->baseUrl().'/agenda')->assertStatus(403);
        $this->postJson($this->baseUrl(), [])->assertStatus(403);
        $this->getJson($this->baseUrl().'/'.$appointment->id)->assertStatus(403);
        $this->postJson($this->baseUrl().'/'.$appointment->id.'/status', ['status' => 'confirmed'])->assertStatus(403);
        $this->deleteJson($this->baseUrl().'/'.$appointment->id)->assertStatus(403);
    }

    public function test_practitioner_overlap_is_rejected_with_409(): void
    {
        Sanctum::actingAs($this->receptionA);

        // 09:00 → 09:30 : OK.
        $firstId = (int) $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'starts_at' => '2026-10-05 09:00:00',
            'ends_at' => '2026-10-05 09:30:00',
            'reason' => 'Consultation cardiologie',
        ])->assertStatus(201)->assertJsonPath('data.status', 'scheduled')->json('data.id');

        // 09:15 → 09:45, même praticien : chevauchement → 409.
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'starts_at' => '2026-10-05 09:15:00',
            'ends_at' => '2026-10-05 09:45:00',
            'reason' => 'Doublon',
        ])->assertStatus(409)->assertJsonPath('error', 'HEALTH_APPOINTMENT_CONFLICT');

        // Même créneau chez un AUTRE praticien : pas de conflit.
        $other = $this->makePractitioner($this->companyA->id, 'Dr Karim L.');
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $other->id,
            'starts_at' => '2026-10-05 09:15:00',
            'ends_at' => '2026-10-05 09:45:00',
            'reason' => 'Autre praticien',
        ])->assertStatus(201);

        // Créneau adjacent (09:30 → 10:00) : pas de chevauchement.
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'starts_at' => '2026-10-05 09:30:00',
            'ends_at' => '2026-10-05 10:00:00',
            'reason' => 'Suivant',
        ])->assertStatus(201);

        // Un rendez-vous ANNULÉ libère son créneau.
        $this->postJson($this->baseUrl().'/'.$firstId.'/status', ['status' => 'cancelled'])
            ->assertStatus(200)->assertJsonPath('data.status', 'cancelled');
        $this->postJson($this->baseUrl(), [
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $this->practitionerA->id,
            'starts_at' => '2026-10-05 09:00:00',
            'ends_at' => '2026-10-05 09:30:00',
            'reason' => 'Créneau repris',
        ])->assertStatus(201);
    }

    public function test_reschedule_is_checked_for_conflicts(): void
    {
        $first = $this->makeAppointment('2026-10-05 09:00:00', '2026-10-05 09:30:00');
        $second = $this->makeAppointment('2026-10-05 10:00:00', '2026-10-05 10:30:00');

        Sanctum::actingAs($this->receptionA);

        // Déplacer le second SUR le premier → 409.
        $this->putJson($this->baseUrl().'/'.$second->id, [
            'starts_at' => '2026-10-05 09:10:00',
            'ends_at' => '2026-10-05 09:40:00',
        ])->assertStatus(409)->assertJsonPath('error', 'HEALTH_APPOINTMENT_CONFLICT');

        // Créneau libre → OK ; fin avant début → 422.
        $this->putJson($this->baseUrl().'/'.$second->id, [
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
        ])->assertStatus(200);
        $this->putJson($this->baseUrl().'/'.$second->id, [
            'ends_at' => '2026-10-05 10:59:00',
        ])->assertStatus(422);

        $this->assertSame(
            'scheduled',
            HealthAppointment::query()->withoutGlobalScope('company')->findOrFail($first->id)->status
        );
    }

    public function test_invalid_status_transitions_are_rejected_with_422(): void
    {
        $appointment = $this->makeAppointment('2026-10-05 09:00:00', '2026-10-05 09:30:00');

        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl().'/'.$appointment->id.'/status';

        // scheduled → completed : interdit (il faut passer par checked_in).
        $this->postJson($url, ['status' => 'completed'])
            ->assertStatus(422)->assertJsonPath('error', 'HEALTH_INVALID_STATUS_TRANSITION');
        // Statut inconnu : 422 de validation.
        $this->postJson($url, ['status' => 'teleported'])->assertStatus(422);

        // Chemin nominal : scheduled → confirmed → checked_in → completed.
        $this->postJson($url, ['status' => 'confirmed'])->assertStatus(200)->assertJsonPath('data.status', 'confirmed');
        // confirmed → scheduled : retour arrière interdit.
        $this->postJson($url, ['status' => 'scheduled'])->assertStatus(422);
        $this->postJson($url, ['status' => 'checked_in'])->assertStatus(200);
        // checked_in → no_show : incohérent (patient déjà arrivé).
        $this->postJson($url, ['status' => 'no_show'])->assertStatus(422);
        $this->postJson($url, ['status' => 'completed'])->assertStatus(200)->assertJsonPath('data.status', 'completed');
        // completed est TERMINAL.
        $this->postJson($url, ['status' => 'cancelled'])->assertStatus(422);
    }

    public function test_practitioner_sees_only_own_agenda_managers_see_all(): void
    {
        $other = $this->makePractitioner($this->companyA->id, 'Dr Karim L.');
        $mine = $this->makeAppointment('2026-10-05 09:00:00', '2026-10-05 09:30:00');
        $others = $this->makeAppointment('2026-10-05 09:00:00', '2026-10-05 09:30:00', $other->id);

        // Le praticien lié à practitionerA ne voit que SON rendez-vous.
        /** @var Employee $doctor */
        $doctor = Employee::query()->findOrFail($this->practitionerA->employee_id);
        Sanctum::actingAs($doctor);

        $this->getJson($this->baseUrl())
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.practitioner_id', $this->practitionerA->id);

        $this->getJson($this->baseUrl().'/agenda?date=2026-10-05&view=day')
            ->assertStatus(200)
            ->assertJsonPath('meta.practitioner_id', $this->practitionerA->id)
            ->assertJsonPath('meta.total', 1);

        // L'agenda d'un AUTRE praticien lui est refusé (403).
        $this->getJson($this->baseUrl().'/agenda?date=2026-10-05&practitioner_id='.$other->id)
            ->assertStatus(403);
        // Le rendez-vous d'un autre praticien : lecture refusée (403).
        $this->getJson($this->baseUrl().'/'.$others->id)->assertStatus(403);
        // Un praticien ne PLANIFIE pas (gestionnaires uniquement).
        $this->postJson($this->baseUrl(), [])->assertStatus(403);

        // Direction : voit tout, agenda de n'importe qui, vue semaine.
        Sanctum::actingAs($this->principalA);
        $this->getJson($this->baseUrl())->assertStatus(200)->assertJsonPath('meta.total', 2);
        $this->getJson($this->baseUrl().'/agenda?date=2026-10-05&view=week&practitioner_id='.$other->id)
            ->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->getJson($this->baseUrl().'/'.$mine->id)->assertStatus(200);
        // Praticien requis pour un gestionnaire non praticien.
        $this->getJson($this->baseUrl().'/agenda?date=2026-10-05')->assertStatus(422);
    }

    public function test_cross_tenant_is_isolated(): void
    {
        $patientB = $this->makePatient($this->companyB->id, 'PAT-2026-0001', 'Sara', 'Alami');
        $practitionerB = $this->makePractitioner($this->companyB->id, 'Dr B.');

        Sanctum::actingAs($this->receptionA);

        // Références d'un autre tenant → 422 (Rule::exists scopée).
        $this->postJson($this->baseUrl(), [
            'patient_id' => $patientB->id,
            'practitioner_id' => $practitionerB->id,
            'starts_at' => '2026-10-05 09:00:00',
            'ends_at' => '2026-10-05 09:30:00',
            'reason' => 'Intrusion',
        ])->assertStatus(422);

        // Rendez-vous du tenant B → 404 depuis A.
        /** @var HealthAppointment $appointmentB */
        $appointmentB = HealthAppointment::query()->forceCreate([
            'company_id' => $this->companyB->id,
            'patient_id' => $patientB->id,
            'practitioner_id' => $practitionerB->id,
            'starts_at' => '2026-10-05 09:00:00',
            'ends_at' => '2026-10-05 09:30:00',
            'reason' => 'RDV tenant B',
            'status' => HealthAppointment::STATUS_SCHEDULED,
        ]);

        $this->getJson($this->baseUrl().'/'.$appointmentB->id)->assertStatus(404);
        $this->putJson($this->baseUrl().'/'.$appointmentB->id, ['reason' => 'X'])->assertStatus(404);
        $this->postJson($this->baseUrl().'/'.$appointmentB->id.'/status', ['status' => 'confirmed'])->assertStatus(404);
        $this->deleteJson($this->baseUrl().'/'.$appointmentB->id)->assertStatus(404);
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

    private function makePractitioner(string $companyId, string $displayName): HealthPractitioner
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

        return $practitioner;
    }

    private function makeAppointment(string $startsAt, string $endsAt, ?int $practitionerId = null): HealthAppointment
    {
        /** @var HealthAppointment $appointment */
        $appointment = HealthAppointment::query()->forceCreate([
            'company_id' => $this->companyA->id,
            'patient_id' => $this->patientA->id,
            'practitioner_id' => $practitionerId ?? $this->practitionerA->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => 'Consultation',
            'status' => HealthAppointment::STATUS_SCHEDULED,
        ]);

        return $appointment;
    }
}
