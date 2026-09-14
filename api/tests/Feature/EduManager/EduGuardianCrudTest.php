<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * EDU-002 / EDU-013 (#5818, #5829) — gestion des responsables légaux.
 *
 * Verrouille le chaînon manquant constaté le 2026-09-14 (audit du parcours
 * client « propriétaire d'école ») : aucune surface ne permettait de CRÉER un
 * responsable légal ni de le rattacher à un élève, alors que
 * `POST /edu-manager/guardians/access-links` exige un `guardian_id` existant
 * et que le portail parents (`/guardian-portal`) était déjà livré. Le portail
 * était donc inatteignable par le produit.
 *
 * Couvre : création (direction), RBAC (employé lambda → 403), rattachement
 * idempotent, isolation cross-tenant (élève ou responsable d'un autre tenant),
 * et le chaînage « créer un responsable → émettre son lien d'accès ».
 */
class EduGuardianCrudTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $lambdaA;

    private EduStudent $studentA;

    private EduStudent $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
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

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-0001',
            'display_name' => 'Amine Belkacem',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;

        /** @var EduStudent $studentB */
        $studentB = EduStudent::query()->create([
            'company_id' => $companyB->id,
            'student_number' => 'STU-0002',
            'display_name' => 'Autre Élève',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentB = $studentB;
    }

    public function test_manager_can_create_guardian_and_link_it_to_a_student(): void
    {
        Sanctum::actingAs($this->principalA);

        $created = $this->postJson('/api/v1/edu-manager/guardians', [
            'first_name' => 'Karim',
            'last_name' => 'Belkacem',
            'contact_reference' => '+213 661 00 00 00',
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
        ])->assertStatus(201);

        $guardianId = (int) $created->json('data.id');
        $this->assertGreaterThan(0, $guardianId);

        // Le contact est une PII chiffrée au repos : la valeur relue passe par
        // le cast `encrypted` et doit rester identique en clair.
        $this->assertSame(
            '+213 661 00 00 00',
            EduGuardian::query()->findOrFail($guardianId)->contact_reference,
        );

        $studentId = (int) $this->studentA->getAttribute('id');

        $this->postJson("/api/v1/edu-manager/students/{$studentId}/guardians", [
            'guardian_id' => $guardianId,
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
            'can_view_grades' => true,
        ])->assertStatus(201)->assertJsonPath('data.can_view_grades', true);

        // Idempotent : le UNIQUE (company, student, guardian) ne crée pas de doublon.
        $this->postJson("/api/v1/edu-manager/students/{$studentId}/guardians", [
            'guardian_id' => $guardianId,
        ])->assertStatus(201);

        $this->assertSame(
            1,
            EduStudentGuardian::query()
                ->where('company_id', $this->companyA->id)
                ->where('student_id', $studentId)
                ->where('guardian_id', $guardianId)
                ->count(),
        );

        // Le chaînage qui était impossible : émettre le lien d'accès du portail
        // parents pour un responsable qui vient d'être créé.
        $this->postJson('/api/v1/edu-manager/guardians/access-links', [
            'guardian_id' => $guardianId,
        ])->assertStatus(201)->assertJsonPath('data.guardian_id', $guardianId);
    }

    public function test_plain_employee_cannot_manage_guardians(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->postJson('/api/v1/edu-manager/guardians', [
            'first_name' => 'Nadia',
            'last_name' => 'Cherif',
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
        ])->assertStatus(403);

        $this->getJson('/api/v1/edu-manager/guardians')->assertStatus(403);
    }

    public function test_guardian_link_is_tenant_isolated(): void
    {
        /** @var EduGuardian $guardianB */
        $guardianB = EduGuardian::query()->create([
            'company_id' => $this->companyB->id,
            'first_name' => 'Amina',
            'last_name' => 'Tahiri',
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
        ]);

        Sanctum::actingAs($this->principalA);
        $studentAId = (int) $this->studentA->getAttribute('id');

        // Responsable d'un AUTRE tenant : refuse en validation (jamais insere).
        $this->postJson("/api/v1/edu-manager/students/{$studentAId}/guardians", [
            'guardian_id' => (int) $guardianB->getAttribute('id'),
        ])->assertStatus(422);

        // Eleve d'un AUTRE tenant : 404 (isolation fail-closed).
        // Le responsable doit etre du MEME tenant que l'acteur : avec un
        // responsable cross-tenant, la validation du `guardian_id` (422)
        // court-circuite avant le controle de tenant de l'eleve
        // (`assertSameTenant` -> abort(404)) et le 404 attendu n'est jamais
        // atteint. On isole donc la propriete testee.
        /** @var EduGuardian $guardianA */
        $guardianA = EduGuardian::query()->create([
            'company_id' => $this->companyA->id,
            'first_name' => 'Yacine',
            'last_name' => 'Mansouri',
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
        ]);

        $this->postJson('/api/v1/edu-manager/students/'.(int) $this->studentB->getAttribute('id').'/guardians', [
            'guardian_id' => (int) $guardianA->getAttribute('id'),
        ])->assertStatus(404);

        $this->assertSame(0, EduStudentGuardian::query()->count());
    }
}
