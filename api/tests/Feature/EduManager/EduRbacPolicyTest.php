<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use Illuminate\Support\Facades\Gate;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5825 (EDU-009) — RBAC et confidentialité scolaire.
 *
 * Verrouille : direction (principal/rh) = gestion complète ; employé lambda
 * = aucun accès ; enseignant = périmètre SES classes (pas d'administration) ;
 * cross-tenant refusé partout ; notes/bulletins protégés.
 */
class EduRbacPolicyTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $plainEmployeeA;

    private Employee $teacherA;

    private Employee $otherTeacherA;

    private EduClass $classA;

    private EduClass $classB;

    private EduSubject $subjectA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var EduSubject $subjectA */
        $subjectA = EduSubject::query()->create([
            'company_id' => $companyA->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);
        $this->subjectA = $subjectA;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $plainEmployeeA */
        $plainEmployeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->plainEmployeeA = $plainEmployeeA;

        /** @var Employee $teacherA */
        $teacherA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->teacherA = $teacherA;

        /** @var Employee $otherTeacherA */
        $otherTeacherA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->otherTeacherA = $otherTeacherA;

        $yearService = app(EduAcademicYearService::class);
        /** @var EduAcademicYear $year */
        $year = $yearService->createYear($principalA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        /** @var EduClass $classA */
        $classA = $yearService->createClass($principalA, [
            'campus_id' => 1,
            'academic_year_id' => (int) $year->getAttribute('id'),
            'code' => 'CL-1',
            'name' => '6ème A',
            'teacher_id' => (int) $teacherA->getAttribute('id'),
        ]);
        $this->classA = $classA;

        /** @var EduClass $classB */
        $classB = $yearService->createClass($principalA, [
            'campus_id' => 1,
            'academic_year_id' => (int) $year->getAttribute('id'),
            'code' => 'CL-2',
            'name' => '6ème B',
            'teacher_id' => (int) $otherTeacherA->getAttribute('id'),
        ]);
        $this->classB = $classB;
    }

    public function test_principal_can_manage_everything(): void
    {
        $this->assertTrue(Gate::forUser($this->principalA)->allows('create', EduAcademicYear::class));
        $this->assertTrue(Gate::forUser($this->principalA)->allows('create', EduClass::class));
        $this->assertTrue(Gate::forUser($this->principalA)->allows('create', EduAdmission::class));
        $this->assertTrue(Gate::forUser($this->principalA)->allows('view', $this->classA));
        $this->assertTrue(Gate::forUser($this->principalA)->allows('view', $this->classB));
    }

    public function test_plain_employee_is_denied_everything(): void
    {
        $this->assertFalse(Gate::forUser($this->plainEmployeeA)->allows('create', EduAcademicYear::class));
        $this->assertFalse(Gate::forUser($this->plainEmployeeA)->allows('create', EduClass::class));
        $this->assertFalse(Gate::forUser($this->plainEmployeeA)->allows('create', EduAdmission::class));
        $this->assertFalse(Gate::forUser($this->plainEmployeeA)->allows('view', $this->classA));
        $this->assertFalse(Gate::forUser($this->plainEmployeeA)->allows('view', $this->classB));
    }

    public function test_teacher_scope_is_limited_to_own_classes(): void
    {
        $teacher = $this->teacherA;

        $this->assertTrue(Gate::forUser($teacher)->allows('view', $this->classA));
        $this->assertFalse(Gate::forUser($teacher)->allows('view', $this->classB));
        $this->assertFalse(Gate::forUser($teacher)->allows('create', EduClass::class));
        $this->assertFalse(Gate::forUser($teacher)->allows('create', EduAcademicYear::class));
        $this->assertFalse(Gate::forUser($teacher)->allows('create', EduAdmission::class));
    }

    public function test_teacher_can_create_assessment_and_grade_for_own_class(): void
    {
        $teacher = $this->teacherA;

        $this->assertTrue(Gate::forUser($teacher)->allows('create', EduAssessment::class));

        /** @var EduAssessment $assessment */
        $assessment = EduAssessment::query()->create([
            'company_id' => $this->companyA->id,
            'class_id' => (int) $this->classA->getAttribute('id'),
            'subject_id' => (int) $this->subjectA->getAttribute('id'),
            'academic_year_id' => 1,
            'title' => 'DS',
            'type' => EduAssessment::TYPE_EXAM,
            'max_score' => 20,
        ]);

        $this->assertTrue(Gate::forUser($teacher)->allows('view', $assessment));
        $this->assertTrue(Gate::forUser($teacher)->allows('update', $assessment));
    }

    public function test_teacher_cannot_manage_other_class_assessment(): void
    {
        $teacher = $this->teacherA;

        /** @var EduAssessment $assessmentB */
        $assessmentB = EduAssessment::query()->create([
            'company_id' => $this->companyA->id,
            'class_id' => (int) $this->classB->getAttribute('id'),
            'subject_id' => (int) $this->subjectA->getAttribute('id'),
            'academic_year_id' => 1,
            'title' => 'DS B',
            'type' => EduAssessment::TYPE_EXAM,
            'max_score' => 20,
        ]);

        $this->assertFalse(Gate::forUser($teacher)->allows('view', $assessmentB));
        $this->assertFalse(Gate::forUser($teacher)->allows('update', $assessmentB));
    }

    public function test_report_card_lifecycle_is_admin_only(): void
    {
        $teacher = $this->teacherA;

        /** @var EduReportCard $card */
        $card = EduReportCard::query()->create([
            'company_id' => $this->companyA->id,
            'student_id' => 1,
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
            'status' => EduReportCard::STATUS_DRAFT,
        ]);

        // Direction : génère/valide/publie.
        $this->assertTrue(Gate::forUser($this->principalA)->allows('validate', $card));
        $this->assertTrue(Gate::forUser($this->principalA)->allows('publish', $card));

        // Enseignant : ni validation ni publication.
        $this->assertFalse(Gate::forUser($teacher)->allows('validate', $card));
        $this->assertFalse(Gate::forUser($teacher)->allows('publish', $card));
    }

    public function test_cross_tenant_is_denied(): void
    {
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        // Un principal du tenant B ne voit pas les classes du tenant A.
        $this->assertFalse(Gate::forUser($principalB)->allows('view', $this->classA));
    }

    public function test_grade_confidentiality(): void
    {
        $teacher = $this->teacherA;
        $other = $this->otherTeacherA;

        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $this->companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Élève A',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        /** @var EduAssessment $assessment */
        $assessment = EduAssessment::query()->create([
            'company_id' => $this->companyA->id,
            'class_id' => (int) $this->classA->getAttribute('id'),
            'subject_id' => (int) $this->subjectA->getAttribute('id'),
            'academic_year_id' => 1,
            'title' => 'DS',
            'type' => EduAssessment::TYPE_EXAM,
            'max_score' => 20,
        ]);

        /** @var EduGrade $grade */
        $grade = EduGrade::query()->create([
            'company_id' => $this->companyA->id,
            'assessment_id' => (int) $assessment->getAttribute('id'),
            'student_id' => (int) $student->getAttribute('id'),
            'score' => 14,
            'status' => EduGrade::STATUS_PUBLISHED,
        ]);

        // L'enseignant de la classe voit la note, pas l'autre enseignant.
        $this->assertTrue(Gate::forUser($teacher)->allows('view', $grade));
        $this->assertFalse(Gate::forUser($other)->allows('view', $grade));
    }
}
