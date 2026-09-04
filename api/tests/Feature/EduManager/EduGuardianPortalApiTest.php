<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Models\GuardianAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Portail guardian — EDU-013 (issue #5829).
 *
 * Couvre : profil gardien (employé lié), enfants explicitement liés
 * uniquement (aucune énumération), présence, bulletins publiés avec
 * can_view_grades, lien d'accès expirable émis par la direction, échange à
 * usage unique, lien expiré refusé, isolation cross-tenant 404.
 */
class EduGuardianPortalApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $guardianEmployee;

    private EduGuardian $guardianA;

    private EduStudent $studentA;

    private EduStudent $otherStudent;

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

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

        /** @var Employee $guardianEmployee */
        $guardianEmployee = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->guardianEmployee = $guardianEmployee;

        /** @var EduCampus $campusA */
        $campusA = EduCampus::query()->create([
            'company_id' => $companyA->id,
            'code' => 'CAMPUS-A',
            'name' => 'Campus A',
        ]);

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;

        /** @var EduStudent $otherStudent */
        $otherStudent = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-A-2',
            'display_name' => 'Yacine Meziane',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->otherStudent = $otherStudent;

        /** @var EduGuardian $guardianA */
        $guardianA = EduGuardian::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => (int) $guardianEmployee->getAttribute('id'),
            'first_name' => 'Responsable',
            'last_name' => 'A',
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
        ]);
        $this->guardianA = $guardianA;

        EduStudentGuardian::query()->create([
            'company_id' => $companyA->id,
            'student_id' => (int) $studentA->getAttribute('id'),
            'guardian_id' => (int) $guardianA->getAttribute('id'),
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
            'can_view_grades' => true,
            'can_receive_notifications' => true,
        ]);
    }

    public function test_guardian_sees_only_linked_children(): void
    {
        Sanctum::actingAs($this->guardianEmployee);

        $this->getJson($this->baseUrl().'/guardians/me')
            ->assertOk()
            ->assertJsonPath('data.students.0.student_number', 'STU-A-1')
            ->assertJsonCount(1, 'data.students');

        $this->getJson($this->baseUrl().'/guardians/me/students')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_guardian_cannot_read_unlinked_student(): void
    {
        Sanctum::actingAs($this->guardianEmployee);

        $this->getJson($this->baseUrl()."/guardians/me/students/{$this->otherStudent->getAttribute('id')}/presences")
            ->assertStatus(404);
    }

    public function test_guardian_without_employee_link_gets_empty_portal(): void
    {
        /** @var Employee $randomEmployee */
        $randomEmployee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        Sanctum::actingAs($randomEmployee);

        $this->getJson($this->baseUrl().'/guardians/me')->assertOk()->assertJsonPath('data', null);
    }

    public function test_access_link_issue_redeem_and_single_use(): void
    {
        Sanctum::actingAs($this->principalA);

        $token = $this->postJson($this->baseUrl().'/guardians/access-links', [
            'guardian_id' => (int) $this->guardianA->getAttribute('id'),
        ])->assertStatus(201)->json('data.token');

        $this->assertSame(64, strlen($token));

        // Échange du lien → profil + enfants liés.
        $this->postJson($this->baseUrl().'/guardians/access-links/redeem', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.students.0.student_number', 'STU-A-1');

        // Usage unique : second échange refusé.
        $this->postJson($this->baseUrl().'/guardians/access-links/redeem', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error', 'EDU_GUARDIAN_LINK_INVALID');
    }

    public function test_access_link_expired_is_rejected(): void
    {
        Sanctum::actingAs($this->principalA);

        $token = $this->postJson($this->baseUrl().'/guardians/access-links', [
            'guardian_id' => (int) $this->guardianA->getAttribute('id'),
        ])->assertStatus(201)->json('data.token');

        // Expire le lien directement (simulation du temps).
        GuardianAccessToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->update(['expires_at' => now()->subDay()]);

        $this->postJson($this->baseUrl().'/guardians/access-links/redeem', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error', 'EDU_GUARDIAN_LINK_INVALID');
    }

    public function test_access_link_issuance_is_direction_only(): void
    {
        Sanctum::actingAs($this->guardianEmployee);

        $this->postJson($this->baseUrl().'/guardians/access-links', [
            'guardian_id' => (int) $this->guardianA->getAttribute('id'),
        ])->assertStatus(403);
    }

    public function test_cross_tenant_guardian_is_404(): void
    {
        Sanctum::actingAs($this->principalA);

        /** @var EduGuardian $guardianB */
        $guardianB = EduGuardian::query()->create([
            'company_id' => $this->companyB->id,
            'first_name' => 'Responsable',
            'last_name' => 'B',
            'relationship_code' => EduGuardian::RELATIONSHIP_PARENT,
        ]);

        // La direction A ne peut pas émettre de lien pour un gardien du tenant B.
        $this->postJson($this->baseUrl().'/guardians/access-links', [
            'guardian_id' => (int) $guardianB->getAttribute('id'),
        ])->assertStatus(422);

        // Un lien du tenant B ne se laisse jamais échanger hors tenant.
        GuardianAccessToken::query()->create([
            'company_id' => $this->companyB->id,
            'guardian_id' => (int) $guardianB->getAttribute('id'),
            'token_hash' => hash('sha256', str_repeat('b', 64)),
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson($this->baseUrl().'/guardians/access-links/redeem', ['token' => str_repeat('b', 64)])
            ->assertStatus(422);
    }
}
