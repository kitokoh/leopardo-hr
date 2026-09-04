<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use App\Modules\EduManager\Infrastructure\Services\EduGradeService;
use App\Modules\EduManager\Infrastructure\Services\EduReportService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5834 (EDU-018) — reporting scolaire (read models agrégés).
 *
 * Verrouille : agrégats de présence, inscriptions par campus, moyennes par
 * matière (notes publiées uniquement), capacité par campus, aucun détail
 * nominatif, isolation tenant.
 */
class EduReportServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private EduCampus $campusA;

    private EduAcademicYear $yearA;

    private Company $companyB;

    private Employee $principalA;

    private EduStudent $studentA;

    private EduClass $classA;

    private EduSubject $math;

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

        /** @var EduCampus $campusA */
        $campusA = EduCampus::query()->create([
            'company_id' => $companyA->id,
            'code' => 'CAMPUS-A',
            'name' => 'Campus A',
        ]);
        $this->campusA = $campusA;
        $yearService = app(EduAcademicYearService::class);
        /** @var EduAcademicYear $year */
        $year = $yearService->createYear($principalA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);
        $this->yearA = $year;

        /** @var EduClass $classA */
        $classA = $yearService->createClass($principalA, [
            'campus_id' => (int) $this->campusA->getAttribute('id'),
            'academic_year_id' => (int) $year->getAttribute('id'),
            'code' => 'CL-1',
            'name' => '6ème A',
            'capacity' => 30,
        ]);
        $this->classA = $classA;

        /** @var EduSubject $math */
        $math = EduSubject::query()->create([
            'company_id' => $companyA->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);
        $this->math = $math;

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;
    }

    public function test_presence_report_aggregates_without_names(): void
    {
        $service = app(EduReportService::class);

        EduAttendance::query()->create([
            'company_id' => $this->companyA->id,
            'class_id' => (int) $this->classA->getAttribute('id'),
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ]);
        EduAttendance::query()->create([
            'company_id' => $this->companyA->id,
            'class_id' => (int) $this->classA->getAttribute('id'),
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-08',
            'status' => EduAttendance::STATUS_ABSENT,
        ]);

        $rows = $service->presence($this->principalA, null, null, null);

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey('present', $rows[0]);
        $this->assertArrayNotHasKey('display_name', $rows[0]);
        $this->assertArrayNotHasKey('student_id', $rows[0]);
    }

    public function test_enrollment_report_groups_by_campus_and_status(): void
    {
        $service = app(EduReportService::class);

        EduAdmission::query()->create([
            'company_id' => $this->companyA->id,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'campus_id' => (int) $this->campusA->getAttribute('id'),
            'admission_number' => 'ADM-1',
            'applicant_first_name' => 'Lina',
            'applicant_last_name' => 'Benali',
            'applied_at' => '2026-06-01',
            'status' => EduAdmission::STATUS_ACCEPTED,
        ]);
        EduAdmission::query()->create([
            'company_id' => $this->companyA->id,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'campus_id' => (int) $this->campusA->getAttribute('id'),
            'admission_number' => 'ADM-2',
            'applicant_first_name' => 'Yacine',
            'applicant_last_name' => 'Meziane',
            'applied_at' => '2026-06-02',
            'status' => EduAdmission::STATUS_NEW,
        ]);

        $rows = $service->enrollment(
            $this->principalA,
            (int) $this->campusA->getAttribute('id'),
            (int) $this->yearA->getAttribute('id')
        );

        $this->assertCount(2, $rows);
        $statuses = array_column($rows, 'status');
        sort($statuses);
        $this->assertSame(['accepted', 'new'], $statuses);
    }

    public function test_results_report_uses_published_grades_only(): void
    {
        $service = app(EduReportService::class);

        /** @var EduAssessment $assessment */
        $assessment = EduAssessment::query()->create([
            'company_id' => $this->companyA->id,
            'class_id' => (int) $this->classA->getAttribute('id'),
            'subject_id' => (int) $this->math->getAttribute('id'),
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'title' => 'DS n°1',
            'type' => EduAssessment::TYPE_EXAM,
            'max_score' => 20,
        ]);

        // Brouillon exclu (unicité assessment+student : une seule note possible).
        /** @var EduGrade $grade */
        $grade = EduGrade::query()->create([
            'company_id' => $this->companyA->id,
            'assessment_id' => (int) $assessment->getAttribute('id'),
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 15,
            'status' => EduGrade::STATUS_DRAFT,
        ]);

        $rows = $service->results($this->principalA, null, null);
        $this->assertCount(0, $rows);

        // Publié inclus.
        app(EduGradeService::class)->publish($this->principalA, $grade);

        $rows = $service->results($this->principalA, null, null);
        $this->assertCount(1, $rows);
        $this->assertSame('15', $rows[0]['average']);
    }

    public function test_capacity_report_sums_class_capacities(): void
    {
        $service = app(EduReportService::class);

        $rows = $service->capacity($this->principalA, null);

        $this->assertCount(1, $rows);
        $this->assertSame(30, $rows[0]['total_capacity']);
        $this->assertSame(1, $rows[0]['class_count']);
    }

    public function test_reports_are_tenant_isolated(): void
    {
        $service = app(EduReportService::class);

        // Élève + présence du tenant B.
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        /** @var EduStudent $studentB */
        $studentB = EduStudent::query()->create([
            'company_id' => $this->companyB->id,
            'student_number' => 'STU-B-1',
            'display_name' => 'Élève B',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        /** @var EduCampus $campusB */
        $campusB = EduCampus::query()->create([
            'company_id' => $this->companyB->id,
            'code' => 'CAMPUS-B',
            'name' => 'Campus B',
        ]);
        /** @var EduAcademicYear $yearB */
        $yearB = EduAcademicYear::query()->create([
            'company_id' => $this->companyB->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        /** @var EduClass $classB */
        $classB = EduClass::query()->create([
            'company_id' => $this->companyB->id,
            'campus_id' => (int) $campusB->getAttribute('id'),
            'academic_year_id' => (int) $yearB->getAttribute('id'),
            'code' => 'CL-B1',
            'name' => '6ème B',
        ]);
        EduAttendance::query()->create([
            'company_id' => $this->companyB->id,
            'class_id' => (int) $classB->getAttribute('id'),

            'student_id' => (int) $studentB->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ]);

        $rowsA = $service->presence($this->principalA, null, null, null);
        $rowsB = $service->presence($principalB, null, null, null);

        $this->assertCount(0, $rowsA);
        $this->assertCount(1, $rowsB);
    }

    public function test_teacher_is_not_admin_for_reports(): void
    {
        /** @var Employee $teacher */
        $teacher = Employee::factory()->create(['company_id' => $this->companyA->id]);

        $this->assertFalse(EduAccess::isAdmin($teacher));
        $this->assertTrue(EduAccess::isAdmin($this->principalA));
    }
}
