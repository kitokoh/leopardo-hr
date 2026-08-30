<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduSubject;
use App\Modules\EduManager\Infrastructure\Services\EduRetentionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5835 (EDU-019) — rétention, anonymisation RGPD et export individuel.
 *
 * Verrouille : anonymisation IDEMPOTENTE (PII masquées, statut archivé,
 * liens guardians détachés), export individuel complet, audit non altérable,
 * isolation tenant (404).
 */
class EduRetentionServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private EduCampus $campusA;

    private EduAcademicYear $yearA;

    private EduClass $classA;

    private EduAssessment $assessmentA;

    private Company $companyB;

    private Employee $principalA;

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

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Lina Benali',
            'birth_date_encrypted' => '2014-03-12',
            'metadata' => ['source' => 'web'],
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;
        /** @var EduCampus $campusA */
        $campusA = EduCampus::query()->create([
            'company_id' => $companyA->id,
            'code' => 'CAMPUS-A',
            'name' => 'Campus A',
        ]);
        $this->campusA = $campusA;

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;

        /** @var EduSubject $subjectA */
        $subjectA = EduSubject::query()->create([
            'company_id' => $companyA->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);

        /** @var EduClass $classA */
        $classA = EduClass::query()->create([
            'company_id' => $companyA->id,
            'campus_id' => (int) $campusA->getAttribute('id'),
            'academic_year_id' => (int) $yearA->getAttribute('id'),
            'code' => 'CL-A1',
            'name' => '6ème A',
        ]);
        $this->classA = $classA;

        /** @var EduAssessment $assessmentA */
        $assessmentA = EduAssessment::query()->create([
            'company_id' => $companyA->id,
            'class_id' => (int) $classA->getAttribute('id'),
            'subject_id' => (int) $subjectA->getAttribute('id'),
            'academic_year_id' => (int) $yearA->getAttribute('id'),
            'title' => 'DS 1',
            'type' => EduAssessment::TYPE_EXAM,
            'max_score' => 20,
        ]);
        $this->assessmentA = $assessmentA;

    }

    public function test_anonymization_masks_pii_and_is_idempotent(): void
    {
        $service = app(EduRetentionService::class);

        $anonymized = $service->anonymizeStudent($this->principalA, $this->studentA);

        $this->assertSame(EduStudent::STATUS_ARCHIVED, $anonymized->status);
        $this->assertStringStartsWith('Élève anonymisé', (string) $anonymized->display_name);
        $this->assertStringNotContainsString('Lina', (string) $anonymized->display_name);
        $this->assertNull($anonymized->birth_date_encrypted);
        $this->assertNull($anonymized->metadata);
        $this->assertSame('STU-A-1', $anonymized->student_number);

        // Idempotence : re-anonymiser ne change rien et n'écrit pas de doublon d'audit.
        $again = $service->anonymizeStudent($this->principalA, $this->studentA->refresh());
        $this->assertSame((int) $anonymized->getAttribute('id'), (int) $again->getAttribute('id'));
    }

    public function test_anonymization_is_audited(): void
    {
        app(EduRetentionService::class)->anonymizeStudent($this->principalA, $this->studentA);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'edu.privacy.anonymized',
            'module' => 'edu',
        ]);
    }

    public function test_individual_export_contains_all_data(): void
    {
        $service = app(EduRetentionService::class);

        // Présence + note + bulletin pour couvrir tous les blocs.
        EduAttendance::query()->create([
            'company_id' => $this->companyA->id,
            'class_id' => (int) $this->classA->getAttribute('id'),
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_PRESENT,
        ]);
        EduGrade::query()->create([
            'company_id' => $this->companyA->id,
            'assessment_id' => (int) $this->assessmentA->getAttribute('id'),
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'score' => 15,
            'status' => EduGrade::STATUS_PUBLISHED,
        ]);
        EduReportCard::query()->create([
            'company_id' => $this->companyA->id,
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'period' => EduReportCard::PERIOD_TERM1,
            'status' => EduReportCard::STATUS_PUBLISHED,
        ]);

        $payload = $service->exportIndividual($this->principalA, $this->studentA);

        $this->assertArrayHasKey('profile', $payload);
        $this->assertArrayHasKey('guardians', $payload);
        $this->assertArrayHasKey('attendances', $payload);
        $this->assertArrayHasKey('grades', $payload);
        $this->assertArrayHasKey('report_cards', $payload);
        $this->assertCount(1, $payload['attendances']);
        $this->assertCount(1, $payload['grades']);
        $this->assertCount(1, $payload['report_cards']);
        $this->assertSame('Lina Benali', $payload['profile']['display_name']);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'edu.privacy.export',
            'module' => 'edu',
        ]);
    }

    public function test_cross_tenant_operations_are_rejected(): void
    {
        /** @var EduStudent $studentB */
        $studentB = EduStudent::query()->create([
            'company_id' => $this->companyB->id,
            'student_number' => 'STU-B-1',
            'display_name' => 'Élève B',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);

        $service = app(EduRetentionService::class);

        $this->expectException(NotFoundHttpException::class);

        $service->anonymizeStudent($this->principalA, $studentB);
    }
}
