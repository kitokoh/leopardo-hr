<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Actions\CorrectEduGradeAction;
use App\Modules\EduManager\Application\Actions\CreateEduAssessmentAction;
use App\Modules\EduManager\Application\Actions\PublishEduAssessmentAction;
use App\Modules\EduManager\Application\Actions\PublishEduGradeAction;
use App\Modules\EduManager\Application\Actions\RecordEduGradeAction;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGradeVersion;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduSubject;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-024a (#8212) — Actions du cas d'usage « évaluation & notes » :
 * création d'évaluation, saisie de note, publication (idempotente) et
 * correction versionnée.
 */
class AssessmentActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

    private EduAssessment $assessmentA;

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

        /** @var EduClass $classA */
        $classA = EduClass::query()->create([
            'company_id' => $companyA->id,
            'academic_year_id' => $yearA->getKey(),
            'code' => 'CL-1',
            'name' => '6ème A',
            'teacher_id' => $principalA->getKey(),
            'status' => EduClass::STATUS_ACTIVE,
        ]);

        /** @var EduSubject $subjectA */
        $subjectA = EduSubject::query()->create([
            'company_id' => $companyA->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);

        /** @var EduAssessment $assessmentA */
        $assessmentA = EduAssessment::query()->create([
            'company_id' => $companyA->id,
            'class_id' => $classA->getKey(),
            'subject_id' => $subjectA->getKey(),
            'academic_year_id' => $yearA->getKey(),
            'title' => 'Devoir surveillé n°1',
            'type' => EduAssessment::TYPE_EXAM,
            'coefficient' => 2,
            'max_score' => 20,
            'assessment_date' => '2026-10-05',
        ]);
        $this->assessmentA = $assessmentA;

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-0001',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;
    }

    public function test_create_assessment_attaches_tenant_and_author(): void
    {
        $assessment = app(CreateEduAssessmentAction::class)->execute($this->principalA, [
            'class_id' => $this->assessmentA->class_id,
            'subject_id' => $this->assessmentA->subject_id,
            'academic_year_id' => $this->assessmentA->academic_year_id,
            'title' => 'Interrogation n°2',
            'type' => EduAssessment::TYPE_QUIZ,
            'coefficient' => 1,
            'max_score' => 10,
            'assessment_date' => '2026-11-12',
        ]);

        $this->assertSame($this->companyA->getKey(), $assessment->getAttribute('company_id'));
        $this->assertSame($this->principalA->getKey(), $assessment->getAttribute('created_by'));
    }

    public function test_record_grade_creates_draft_grade(): void
    {
        $grade = app(RecordEduGradeAction::class)->execute($this->principalA, $this->assessmentA, [
            'student_id' => $this->studentA->getKey(),
            'score' => 15,
            'comment' => 'Bien',
        ]);

        $this->assertSame(EduGrade::STATUS_DRAFT, $grade->status);
        $this->assertEquals(15.0, $grade->score);
        $this->assertEquals(1, $grade->version);
    }

    public function test_publish_grade_marks_published(): void
    {
        $grade = app(RecordEduGradeAction::class)->execute($this->principalA, $this->assessmentA, [
            'student_id' => $this->studentA->getKey(),
            'score' => 15,
        ]);

        $published = app(PublishEduGradeAction::class)->execute($this->principalA, $grade);

        $this->assertSame(EduGrade::STATUS_PUBLISHED, $published->status);
        $this->assertNotNull($published->published_at);
    }

    public function test_correct_grade_increments_version_and_journals(): void
    {
        $grade = app(RecordEduGradeAction::class)->execute($this->principalA, $this->assessmentA, [
            'student_id' => $this->studentA->getKey(),
            'score' => 15,
        ]);

        $corrected = app(CorrectEduGradeAction::class)->execute($this->principalA, $grade, [
            'score' => 18,
            'comment' => 'Erreur de saisie',
        ]);

        $this->assertEquals(2, $corrected->version);
        $this->assertEquals(18.0, $corrected->score);
        $this->assertSame(EduGrade::STATUS_CORRECTED, $corrected->status);
        $this->assertDatabaseHas('edu_grade_versions', [
            'grade_id' => $grade->getKey(),
            'version' => 2,
        ]);
        $this->assertSame(1, EduGradeVersion::query()
            ->where('grade_id', $grade->getAttribute('id'))
            ->count());
    }

    public function test_publish_assessment_is_idempotent(): void
    {
        $action = app(PublishEduAssessmentAction::class);

        $first = $action->execute($this->assessmentA);
        $this->assertNotNull($first->published_at);

        $second = $action->execute($first);
        $this->assertNotNull($second->published_at);

        // Deuxième publication → le timestamp d'origine est conservé.
        $this->assertSame(
            $first->published_at->toIso8601String(),
            $second->published_at->toIso8601String(),
        );
    }
}
