<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Actions\EnrollEduStudentAction;
use App\Modules\EduManager\Application\Actions\UnenrollEduStudentAction;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduClassEnrollment;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-024a (#8212) — Actions du cas d'usage « inscription à une classe » :
 * inscription idempotente (UNIQUE company/class/student) et désinscription
 * douce (historique conservé).
 */
class EnrollmentActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

    private EduClass $classA;

    private EduStudent $studentA;

    private EduAcademicYear $yearA;

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

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;

        /** @var EduClass $classA */
        $classA = EduClass::query()->create([
            'company_id' => $companyA->id,
            'academic_year_id' => $yearA->getKey(),
            'code' => 'CP-A',
            'name' => 'CP A',
            'teacher_id' => $principalA->getKey(),
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

    public function test_enroll_student_creates_active_enrollment_for_session_tenant(): void
    {
        $enrollment = app(EnrollEduStudentAction::class)->execute($this->principalA, $this->classA, [
            'student_id' => $this->studentA->getKey(),
            'academic_year_id' => $this->yearA->getKey(),
        ]);

        $this->assertSame($this->companyA->getKey(), $enrollment->getAttribute('company_id'));
        $this->assertSame(EduClassEnrollment::STATUS_ACTIVE, $enrollment->status);
        $this->assertSame($this->principalA->getKey(), $enrollment->getAttribute('enrolled_by'));
    }

    public function test_enroll_student_is_idempotent_on_unique_violation(): void
    {
        $data = [
            'student_id' => $this->studentA->getKey(),
            'academic_year_id' => $this->yearA->getKey(),
        ];

        $first = app(EnrollEduStudentAction::class)->execute($this->principalA, $this->classA, $data);
        $second = app(EnrollEduStudentAction::class)->execute($this->principalA, $this->classA, $data);

        // Double envoi → la même inscription, aucune ligne dupliquée.
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, EduClassEnrollment::query()
            ->where('company_id', $this->companyA->id)
            ->where('class_id', $this->classA->getAttribute('id'))
            ->where('student_id', $this->studentA->getAttribute('id'))
            ->count());
    }

    public function test_unenroll_student_soft_deactivates_enrollment(): void
    {
        $enrollment = app(EnrollEduStudentAction::class)->execute($this->principalA, $this->classA, [
            'student_id' => $this->studentA->getKey(),
            'academic_year_id' => $this->yearA->getKey(),
        ]);

        $result = app(UnenrollEduStudentAction::class)->execute($enrollment);

        $this->assertSame(EduClassEnrollment::STATUS_INACTIVE, $result->status);
        // Historique conservé : la ligne existe toujours.
        $this->assertDatabaseHas('edu_class_enrollments', [
            'id' => $enrollment->getKey(),
            'status' => EduClassEnrollment::STATUS_INACTIVE,
        ]);
    }
}
