<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGradeVersion;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5823 (EDU-007) — évaluations et notes versionnées.
 *
 * Verrouille : schéma tenant, barème borné [0, max_score], saisie
 * idempotente, publication idempotente, correction VERSIONNÉE (journal
 * edu_grade_versions, version++), isolation tenant (404).
 */
class EduGradeServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $teacherA;

    private EduAssessment $assessmentA;

    private EduStudent $studentA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var Employee $teacherA */
        $teacherA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->teacherA = $teacherA;

        $yearService = app(EduAcademicYearService::class);
        /** @var EduAcademicYear $year */
        $year = $yearService->createYear(Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]), [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        /** @var EduClass $class */
        $class = $yearService->createClass(Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]), [
            'campus_id' => 1,
            'academic_year_id' => (int) $year->getAttribute('id'),
            'code' => 'CL-1',
            'name' => '6ème A',
            'teacher_id' => (int) $teacherA->getAttribute('id'),
        ]);

        /** @var EduSubject $subject */
        $subject = EduSubject::query()->create([
            'company_id' => $companyA->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);

        /** @var EduAssessment $assessmentA */
        $assessmentA = EduAssessment::query()->create([
            'company_id' => $companyA->id,
            'class_id' => (int) $class->getAttribute('id'),
            'subject_id' => (int) $subject->getAttribute('id'),
            'academic_year_id' => (int) $year->getAttribute('id'),
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
            'student_number' => 'STU-A-1',
            'display_name' => 'Élève A',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;
    }

    public function test_assessment_tables_exist_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('edu_assessments'));
        $this->assertTrue(Schema::hasTable('edu_grades'));
        $this->assertTrue(Schema::hasTable('edu_grade_versions'));
    }

    public function test_grade_out_of_range_is_rejected(): void
    {
        $this->expectExceptionMessage('EDU_GRADE_OUT_OF_RANGE');

        app(EduGradeService::class)->grade($this->teacherA, $this->assessmentA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 21,
        ]);
    }

    public function test_grade_is_idempotent(): void
    {
        $service = app(EduGradeService::class);

        $first = $service->grade($this->teacherA, $this->assessmentA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 14.5,
        ]);
        $second = $service->grade($this->teacherA, $this->assessmentA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 14.5,
        ]);

        $this->assertSame((int) $first->getAttribute('id'), (int) $second->getAttribute('id'));
        $this->assertSame(1, EduGrade::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame(1, (int) $first->version);
    }

    public function test_publish_is_idempotent(): void
    {
        $service = app(EduGradeService::class);
        $grade = $service->grade($this->teacherA, $this->assessmentA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 12,
        ]);

        $published = $service->publish($this->teacherA, $grade);
        $publishedAgain = $service->publish($this->teacherA, $published);

        $this->assertNotNull($publishedAgain->published_at);
        $this->assertSame(EduGrade::STATUS_PUBLISHED, $publishedAgain->status);
    }

    public function test_correction_is_versioned(): void
    {
        $service = app(EduGradeService::class);
        $grade = $service->grade($this->teacherA, $this->assessmentA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 10,
        ]);

        $corrected = $service->correct($this->teacherA, $grade, [
            'score' => 15,
            'comment' => 'Erreur de report',
        ]);

        $this->assertSame('15.00', $corrected->score);
        $this->assertSame(2, (int) $corrected->version);
        $this->assertSame(EduGrade::STATUS_CORRECTED, $corrected->status);

        $versions = EduGradeVersion::query()->where('company_id', $this->companyA->id)->get();
        $this->assertCount(1, $versions);
        $this->assertSame(2, (int) $versions->first()->version);
        $this->assertSame('15.00', $versions->first()->score);
    }

    public function test_correction_keeps_full_history(): void
    {
        $service = app(EduGradeService::class);
        $grade = $service->grade($this->teacherA, $this->assessmentA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 10,
        ]);
        $service->correct($this->teacherA, $grade, ['score' => 15]);
        $grade->refresh();
        $service->correct($this->teacherA, $grade, ['score' => 16]);

        $versions = EduGradeVersion::query()
            ->where('company_id', $this->companyA->id)
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertSame([2, 3], $versions->pluck('version')->map(fn ($v): int => (int) $v)->all());
    }

    public function test_other_tenant_assessment_is_rejected(): void
    {
        /** @var EduAssessment $assessmentB */
        $assessmentB = EduAssessment::query()->create([
            'company_id' => $this->companyB->id,
            'class_id' => 1,
            'subject_id' => 1,
            'academic_year_id' => 1,
            'title' => 'DS B',
            'type' => EduAssessment::TYPE_EXAM,
            'max_score' => 20,
        ]);

        $this->expectExceptionCode(404);

        app(EduGradeService::class)->grade($this->teacherA, $assessmentB, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 12,
        ]);
    }
}
