<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5827 (EDU-011) — inscriptions d'élèves dans les classes.
 *
 * Verrouille : inscription idempotente (UNIQUE company/class/student),
 * effectifs visibles par l'enseignant de la classe, gestion réservée à la
 * direction, désinscription soft (status inactive), isolation cross-tenant.
 */
class EduClassEnrollmentTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $teacherA;

    private Employee $lambdaA;

    private EduClass $classA;

    private EduStudent $studentA;

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

        /** @var Employee $teacherA */
        $teacherA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->teacherA = $teacherA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);

        /** @var EduClass $classA */
        $classA = EduClass::query()->create([
            'company_id' => $companyA->id,
            'academic_year_id' => (int) $yearA->getAttribute('id'),
            'code' => 'CP-A',
            'name' => 'CP A',
            'teacher_id' => (int) $teacherA->getAttribute('id'),
            'status' => EduClass::STATUS_ACTIVE,
        ]);
        $this->classA = $classA;

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-0001',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;
    }

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    private function yearId(): int
    {
        return (int) EduAcademicYear::query()->where('company_id', $this->companyA->id)->value('id');
    }

    public function test_enrollment_is_idempotent_and_listed(): void
    {
        Sanctum::actingAs($this->principalA);
        $url = $this->baseUrl();
        $classId = (int) $this->classA->getAttribute('id');
        $studentId = (int) $this->studentA->getAttribute('id');

        $payload = ['student_id' => $studentId, 'academic_year_id' => $this->yearId()];

        $first = $this->postJson($url."/classes/{$classId}/enrollments", $payload)->assertStatus(201);
        $second = $this->postJson($url."/classes/{$classId}/enrollments", $payload)->assertStatus(201);

        $this->assertSame($first->json('data.enrollment_id'), $second->json('data.enrollment_id'));
        $this->assertSame(1, EduClassEnrollment::query()->where('company_id', $this->companyA->id)->count());

        $this->getJson($url."/classes/{$classId}/enrollments")
            ->assertOk()
            ->assertJsonPath('data.students.0.student.display_name', 'Lina Benali')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_enrollment_rbac_and_soft_unenroll(): void
    {
        $classId = (int) $this->classA->getAttribute('id');
        $studentId = (int) $this->studentA->getAttribute('id');
        $payload = ['student_id' => $studentId, 'academic_year_id' => $this->yearId()];

        // Employé lambda : 403.
        Sanctum::actingAs($this->lambdaA);
        $this->postJson($this->baseUrl()."/classes/{$classId}/enrollments", $payload)->assertStatus(403);

        // L'enseignant de la classe consulte les effectifs (lecture).
        Sanctum::actingAs($this->teacherA);
        $this->getJson($this->baseUrl()."/classes/{$classId}/enrollments")->assertOk();
        // Mais ne peut pas inscrire (direction uniquement).
        $this->postJson($this->baseUrl()."/classes/{$classId}/enrollments", $payload)->assertStatus(403);

        // Direction : inscription puis désinscription soft.
        Sanctum::actingAs($this->principalA);
        $enrollmentId = $this->postJson($this->baseUrl()."/classes/{$classId}/enrollments", $payload)
            ->assertStatus(201)->json('data.enrollment_id');

        $this->deleteJson($this->baseUrl()."/class-enrollments/{$enrollmentId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->getJson($this->baseUrl()."/classes/{$classId}/enrollments")->assertJsonPath('meta.total', 0);
    }

    public function test_enrollment_is_tenant_isolated(): void
    {
        $classId = (int) $this->classA->getAttribute('id');

        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);

        $this->postJson($this->baseUrl()."/classes/{$classId}/enrollments", [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'academic_year_id' => $this->yearId(),
        ])->assertStatus(404);
    }
}
