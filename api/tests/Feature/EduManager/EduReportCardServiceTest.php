<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduReportCardLine;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;
use App\Modules\EduManager\Infrastructure\Services\EduReportCardService;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5824 (EDU-008) — bulletins : génération, validation, publication.
 *
 * Verrouille : schéma tenant, génération idempotente (lignes recalculées),
 * moyenne calculée depuis les notes publiées, validation direction,
 * publication → bulletin verrouillé (EDU_REPORT_CARD_LOCKED), période
 * inconnue refusée, isolation tenant (404).
 */
class EduReportCardServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $teacherA;

    private EduStudent $studentA;

    private EduAssessment $assessmentA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
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

        $yearService = app(EduAcademicYearService::class);
        /** @var EduAcademicYear $year */
        $year = $yearService->createYear($principalA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        /** @var EduClass $class */
        $class = $yearService->createClass($principalA, [
            'campus_id' => 1,
            'academic_year_id' => (int) $year->getAttribute('id'),
            'code' => 'CL-1',
            'name' => '6ème A',
            'teacher_id' => (int) $teacherA->getAttribute('id'),
        ]);

        /** @var EduSubject $math */
        $math = EduSubject::query()->create([
            'company_id' => $companyA->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);

        /** @var EduAssessment $assessmentA */
        $assessmentA = EduAssessment::query()->create([
            'company_id' => $companyA->id,
            'class_id' => (int) $class->getAttribute('id'),
            'subject_id' => (int) $math->getAttribute('id'),
            'academic_year_id' => (int) $year->getAttribute('id'),
            'title' => 'DS n°1',
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

    public function test_report_card_tables_exist_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('edu_report_cards'));
        $this->assertTrue(Schema::hasTable('edu_report_card_lines'));
    }

    public function test_generate_recomputes_lines_from_published_grades(): void
    {
        $gradeService = app(EduGradeService::class);
        $grade = $gradeService->grade($this->teacherA, $this->assessmentA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 14,
        ]);
        $gradeService->publish($this->teacherA, $grade);

        $service = app(EduReportCardService::class);
        $card = $service->generate($this->principalA, $this->studentA, [
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
        ]);

        $this->assertSame(EduReportCard::STATUS_DRAFT, $card->status);
        $this->assertNotNull($card->generated_at);

        $line = EduReportCardLine::query()->where('report_card_id', (int) $card->getAttribute('id'))->firstOrFail();
        $this->assertSame('14.00', $line->average);
        $this->assertSame('2', $line->coefficient);
        $this->assertSame(1, (int) $line->assessment_count);
    }

    public function test_generate_is_idempotent(): void
    {
        $service = app(EduReportCardService::class);

        $first = $service->generate($this->principalA, $this->studentA, [
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
        ]);
        $second = $service->generate($this->principalA, $this->studentA, [
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
        ]);

        $this->assertSame((int) $first->getAttribute('id'), (int) $second->getAttribute('id'));
        $this->assertSame(1, EduReportCard::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_generate_rejects_unknown_period(): void
    {
        $this->expectExceptionMessage('EDU_REPORT_CARD_PERIOD');

        app(EduReportCardService::class)->generate($this->principalA, $this->studentA, [
            'academic_year_id' => 1,
            'period' => 'semester9',
        ]);
    }

    public function test_validate_then_publish_locks_the_card(): void
    {
        $service = app(EduReportCardService::class);
        $card = $service->generate($this->principalA, $this->studentA, [
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
        ]);

        $validated = $service->validate($this->principalA, $card);
        $this->assertSame(EduReportCard::STATUS_VALIDATED, $validated->status);
        $this->assertNotNull($validated->validated_at);
        $this->assertSame((int) $this->principalA->getAttribute('id'), (int) $validated->validated_by);

        $published = $service->publish($this->principalA, $validated);
        $this->assertSame(EduReportCard::STATUS_PUBLISHED, $published->status);
        $this->assertTrue($published->isPublished());

        $this->expectExceptionMessage('EDU_REPORT_CARD_LOCKED');

        $service->generate($this->principalA, $published->student()->firstOrFail(), [
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
        ]);
    }

    public function test_publish_requires_validation(): void
    {
        $service = app(EduReportCardService::class);
        $card = $service->generate($this->principalA, $this->studentA, [
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
        ]);

        $this->expectExceptionMessage('EDU_REPORT_CARD_NOT_VALIDATED');

        $service->publish($this->principalA, $card);
    }

    public function test_other_tenant_student_is_rejected(): void
    {
        /** @var EduStudent $studentB */
        $studentB = EduStudent::query()->create([
            'company_id' => $this->companyB->id,
            'student_number' => 'STU-B-1',
            'display_name' => 'Élève B',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        $this->expectExceptionCode(404);

        app(EduReportCardService::class)->generate($this->principalA, $studentB, [
            'academic_year_id' => 1,
            'period' => EduReportCard::PERIOD_TERM1,
        ]);
    }
}
