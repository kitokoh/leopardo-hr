<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API des rendez-vous & agenda — HC-004 (#7788, BC-30).
 *
 * Matrice : 401 / 403 solution inactive / 403 lambda / CRUD réception /
 * conflit praticien 409 (adjacent OK, annulé ne bloque pas) / machine à
 * états (chaîne valide, transition invalide 422) / praticien restreint à
 * SON agenda et sans création / 404 cross-tenant / références 422.
 */
class HealthAppointmentTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $receptionA;

    private Employee $lambdaA;

    private Employee $practitionerEmployeeA;

    private HealthPractitioner $practitionerA;

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

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['healthmanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $receptionA */
        $receptionA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->receptionA = $receptionA;
        HealthStaffRole::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => (int) $receptionA->getAttribute('id'),
            'role' => HealthStaffRole::ROLE_RECEPTION,
        ]);

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var Employee $practitionerEmployeeA */
        $practitionerEmployeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->practitionerEmployeeA = $practitionerEmployeeA;
        $this->practitionerA = $this->makePractitioner($companyA, $practitionerEmployeeA);

        $this->patientA = $this->makePatient($companyA);
    }

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
            'mrn' => 'PAT-'.now()->format('Y').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'full_name' => 'Patient Test',
            'sex' => HealthPatient::SEX_MALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);

        return $patient;
    }

    /**
     * @return array<string, mixed>
     */
    private function slotPayload(string $startsAt, string $endsAt, ?int $practitionerId = null): array
    {
        return [
            'patient_id' => (int) $this->patientA->getAttribute('id'),
            'practitioner_id' => $practitionerId ?? (int) $this->practitionerA->getAttribute('id'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => 'Consultation de suivi',
        ];
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/appointments')->assertStatus(401);
        $this->postJson($this->baseUrl().'/appointments', [])->assertStatus(401);
        $this->getJson($this->baseUrl().'/appointments/agenda')->assertStatus(401);
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

        $this->getJson($this->baseUrl().'/appointments')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/appointments')->assertStatus(403);
        $this->getJson($this->baseUrl().'/appointments/agenda')->assertStatus(403);
        $this->postJson(
            $this->baseUrl().'/appointments',
            $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 09:30:00')
        )->assertStatus(403);
    }

    public function test_reception_crud_and_filters(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $created = $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 09:30:00'))
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'scheduled');
        $appointmentId = $created->json('data.id');

        // Fiche
        $this->getJson($url.'/appointments/'.$appointmentId)
            ->assertStatus(200)
            ->assertJsonPath('data.reason', 'Consultation de suivi');

        // Mise à jour (déplacement de créneau, pas de conflit avec soi-même).
        $this->putJson($url.'/appointments/'.$appointmentId, [
            'starts_at' => '2026-03-02 10:00:00',
            'ends_at' => '2026-03-02 10:30:00',
        ])->assertStatus(200);

        // Filtres index : date / practitioner_id / patient_id / status.
        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-03 09:00:00', '2026-03-03 09:30:00'))
            ->assertStatus(201);

        $byDate = $this->getJson($url.'/appointments?date=2026-03-02')->assertStatus(200);
        $this->assertSame(1, $byDate->json('meta.total'));

        $byPractitioner = $this->getJson($url.'/appointments?practitioner_id='.$this->practitionerA->getAttribute('id'))
            ->assertStatus(200);
        $this->assertSame(2, $byPractitioner->json('meta.total'));

        $byPatient = $this->getJson($url.'/appointments?patient_id='.$this->patientA->getAttribute('id'))
            ->assertStatus(200);
        $this->assertSame(2, $byPatient->json('meta.total'));

        $byStatus = $this->getJson($url.'/appointments?status=scheduled')->assertStatus(200);
        $this->assertSame(2, $byStatus->json('meta.total'));

        $this->assertSame(0, $this->getJson($url.'/appointments?status=completed')->json('meta.total'));
    }

    public function test_practitioner_overlap_is_409_conflict(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 10:00:00'))
            ->assertStatus(201);

        // Chevauchement partiel → 409 HEALTH_APPOINTMENT_CONFLICT.
        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:30:00', '2026-03-02 10:30:00'))
            ->assertStatus(409)
            ->assertJsonPath('error', 'HEALTH_APPOINTMENT_CONFLICT');

        // Même créneau chez un AUTRE praticien : OK.
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $other = $this->makePractitioner($this->companyA, $otherEmployee);
        $this->postJson(
            $url.'/appointments',
            $this->slotPayload('2026-03-02 09:30:00', '2026-03-02 10:30:00', (int) $other->getAttribute('id'))
        )->assertStatus(201);

        // Déplacement en UPDATE vers un créneau occupé → 409 aussi.
        $movable = $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 14:00:00', '2026-03-02 15:00:00'))
            ->assertStatus(201)->json('data.id');
        $this->putJson($url.'/appointments/'.$movable, [
            'starts_at' => '2026-03-02 09:30:00',
            'ends_at' => '2026-03-02 10:30:00',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'HEALTH_APPOINTMENT_CONFLICT');
    }

    public function test_adjacent_slots_do_not_conflict(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 10:00:00'))
            ->assertStatus(201);

        // ends_at == starts_at du suivant : bord à bord, PAS de conflit.
        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 10:00:00', '2026-03-02 11:00:00'))
            ->assertStatus(201);
    }

    public function test_cancelled_slot_does_not_block(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $cancelled = $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 10:00:00'))
            ->assertStatus(201)->json('data.id');
        $this->postJson($url.'/appointments/'.$cancelled.'/status', ['status' => 'cancelled'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // Le créneau annulé ne bloque plus l'agenda du praticien.
        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 10:00:00'))
            ->assertStatus(201);
    }

    public function test_valid_transition_chain_and_invalid_transition_422(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $appointmentId = $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 09:30:00'))
            ->assertStatus(201)->json('data.id');

        // Transition invalide : scheduled → completed (422).
        $this->postJson($url.'/appointments/'.$appointmentId.'/status', ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVALID_TRANSITION');

        // Chaîne valide : scheduled → confirmed → checked_in → completed.
        foreach (['confirmed', 'checked_in', 'completed'] as $status) {
            $this->postJson($url.'/appointments/'.$appointmentId.'/status', ['status' => $status])
                ->assertStatus(200)
                ->assertJsonPath('data.status', $status);
        }

        // Terminal : completed → * refusé.
        $this->postJson($url.'/appointments/'.$appointmentId.'/status', ['status' => 'cancelled'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'HEALTH_INVALID_TRANSITION');

        // Statut inconnu → 422 (validation).
        $this->postJson($url.'/appointments/'.$appointmentId.'/status', ['status' => 'nonsense'])
            ->assertStatus(422);
    }

    public function test_practitioner_cannot_create_but_can_transition_own(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();
        $appointmentId = $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 09:30:00'))
            ->assertStatus(201)->json('data.id');

        Sanctum::actingAs($this->practitionerEmployeeA);

        // Création interdite au praticien (policy deny-by-default).
        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 11:00:00', '2026-03-02 11:30:00'))
            ->assertStatus(403);

        // Mais il transitionne SES rendez-vous (confirmed → ... → completed).
        foreach (['confirmed', 'checked_in', 'completed'] as $status) {
            $this->postJson($url.'/appointments/'.$appointmentId.'/status', ['status' => $status])
                ->assertStatus(200)
                ->assertJsonPath('data.status', $status);
        }
    }

    public function test_practitioner_sees_only_own_agenda_reception_sees_all(): void
    {
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $otherPractitioner = $this->makePractitioner($this->companyA, $otherEmployee);

        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $ownId = $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 09:30:00'))
            ->assertStatus(201)->json('data.id');
        $otherId = $this->postJson(
            $url.'/appointments',
            $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 09:30:00', (int) $otherPractitioner->getAttribute('id'))
        )->assertStatus(201)->json('data.id');

        // Réception : voit TOUT (index + agenda sans restriction).
        $this->assertSame(2, $this->getJson($url.'/appointments')->json('meta.total'));
        $receptionAgenda = $this->getJson($url.'/appointments/agenda?from=2026-03-01&to=2026-03-08')->assertStatus(200);
        $this->assertCount(2, (array) $receptionAgenda->json('data'));

        // Praticien : index et agenda restreints à SON practitioner_id,
        // même en demandant explicitement celui d'un confrère.
        Sanctum::actingAs($this->practitionerEmployeeA);

        $index = $this->getJson($url.'/appointments?practitioner_id='.$otherPractitioner->getAttribute('id'))
            ->assertStatus(200);
        $this->assertSame(1, $index->json('meta.total'));
        $index->assertJsonPath('data.0.id', $ownId);

        $agenda = $this->getJson(
            $url.'/appointments/agenda?practitioner_id='.$otherPractitioner->getAttribute('id').'&from=2026-03-01&to=2026-03-08'
        )->assertStatus(200);
        $this->assertCount(1, (array) $agenda->json('data'));
        $agenda->assertJsonPath('data.0.id', $ownId)
            ->assertJsonPath('meta.practitioner_id', (int) $this->practitionerA->getAttribute('id'));

        // Fiche d'un confrère → 403 (policy view).
        $this->getJson($url.'/appointments/'.$otherId)->assertStatus(403);
    }

    public function test_cross_tenant_appointment_is_404(): void
    {
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $this->companyB->id]);
        $practitionerB = $this->makePractitioner($this->companyB, $employeeB);
        /** @var HealthPatient $patientB */
        $patientB = HealthPatient::query()->create([
            'company_id' => $this->companyB->id,
            'mrn' => 'PAT-'.now()->format('Y').'-0001',
            'full_name' => 'Patient B',
            'sex' => HealthPatient::SEX_FEMALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);
        /** @var HealthAppointment $foreign */
        $foreign = HealthAppointment::query()->create([
            'company_id' => $this->companyB->id,
            'patient_id' => (int) $patientB->getAttribute('id'),
            'practitioner_id' => (int) $practitionerB->getAttribute('id'),
            'starts_at' => '2026-03-02 09:00:00',
            'ends_at' => '2026-03-02 09:30:00',
            'status' => HealthAppointment::STATUS_SCHEDULED,
        ]);

        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $this->getJson($url.'/appointments/'.$foreign->getAttribute('id'))->assertStatus(404);
        $this->putJson($url.'/appointments/'.$foreign->getAttribute('id'), ['reason' => 'X'])->assertStatus(404);
        $this->postJson($url.'/appointments/'.$foreign->getAttribute('id').'/status', ['status' => 'confirmed'])
            ->assertStatus(404);

        // Références cross-tenant à la création → 422 (exists scopé tenant).
        $this->postJson($url.'/appointments', [
            'patient_id' => (int) $patientB->getAttribute('id'),
            'practitioner_id' => (int) $practitionerB->getAttribute('id'),
            'starts_at' => '2026-03-02 09:00:00',
            'ends_at' => '2026-03-02 09:30:00',
        ])->assertStatus(422);
    }

    public function test_time_and_reference_validation_is_422(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        // ends_at <= starts_at → 422.
        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 10:00:00', '2026-03-02 09:00:00'))
            ->assertStatus(422);
        $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 10:00:00', '2026-03-02 10:00:00'))
            ->assertStatus(422);

        // Références inconnues → 422.
        $this->postJson($url.'/appointments', array_merge(
            $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 09:30:00'),
            ['patient_id' => 999999]
        ))->assertStatus(422);

        // En UPDATE, l'état fusionné doit rester cohérent (ends_at figé
        // avant le nouveau starts_at → 422).
        $appointmentId = $this->postJson($url.'/appointments', $this->slotPayload('2026-03-02 09:00:00', '2026-03-02 09:30:00'))
            ->assertStatus(201)->json('data.id');
        $this->putJson($url.'/appointments/'.$appointmentId, ['starts_at' => '2026-03-02 09:45:00'])
            ->assertStatus(422);
    }
}
