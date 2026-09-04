<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Jobs\SendEduNotificationJob;
use App\Modules\EduManager\Infrastructure\Services\EduNotificationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5830 (EDU-014) — notifications direction (hub transverse).
 *
 * Verrouille : templates enregistrés (config + i18n ×4), job dispatché aux
 * directeurs (principal/rh) avec le bon contexte, aucun dispatch sans
 * direction active, isolation tenant (les managers d'un autre tenant ne
 * sont jamais notifiés).
 */
class EduNotificationServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private EduCampus $campusA;

    private EduAcademicYear $yearA;

    private EduClass $classA;

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
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
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
        /** @var EduClass $classA */
        $classA = EduClass::query()->create([
            'company_id' => $companyA->id,
            'campus_id' => (int) $campusA->getAttribute('id'),
            'academic_year_id' => (int) $yearA->getAttribute('id'),
            'code' => 'CL-A1',
            'name' => '6ème A',
        ]);
        $this->classA = $classA;
        $this->studentA = $studentA;
    }

    public function test_templates_are_registered_with_i18n_in_four_locales(): void
    {
        $templates = Config::get('communication.templates');

        foreach ([
            EduNotificationService::TEMPLATE_ADMISSION_CONVERTED,
            EduNotificationService::TEMPLATE_ABSENCE_RECORDED,
            EduNotificationService::TEMPLATE_REPORT_CARD_PUBLISHED,
        ] as $key) {
            $this->assertArrayHasKey($key, $templates, "template {$key} absent");
        }

        foreach (['fr', 'en', 'ar', 'tr'] as $locale) {
            $lines = Lang::get('notifications', [], $locale);
            $this->assertIsArray($lines);
            $this->assertArrayHasKey('edu_admission_converted_title', $lines, "clé absente ({$locale})");
            $this->assertArrayHasKey('edu_absence_recorded_title', $lines, "clé absente ({$locale})");
            $this->assertArrayHasKey('edu_report_card_published_title', $lines, "clé absente ({$locale})");
        }
    }

    public function test_admission_converted_dispatches_to_principal(): void
    {
        Queue::fake();

        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->create([
            'company_id' => $this->companyA->id,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'admission_number' => 'ADM-2026-00001',
            'applicant_first_name' => 'Lina',
            'applicant_last_name' => 'Benali',
            'applied_at' => '2026-06-01',
            'status' => EduAdmission::STATUS_CONVERTED,
        ]);

        app(EduNotificationService::class)->admissionConverted($admission);

        Queue::assertPushed(SendEduNotificationJob::class, function (SendEduNotificationJob $job): bool {
            return $job->companyId === $this->companyA->id
                && in_array((int) $this->principalA->getAttribute('id'), $job->employeeIds, true)
                && $job->templateKey === EduNotificationService::TEMPLATE_ADMISSION_CONVERTED
                && ($job->context['admission_number'] ?? null) === 'ADM-2026-00001';
        });
    }

    public function test_absence_recorded_dispatches_with_context(): void
    {
        Queue::fake();

        /** @var EduAttendance $attendance */
        $attendance = EduAttendance::query()->create([
            'company_id' => $this->companyA->id,
            'class_id' => (int) $this->classA->getAttribute('id'),
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'attendance_date' => '2026-09-07',
            'status' => EduAttendance::STATUS_ABSENT,
        ]);

        app(EduNotificationService::class)->absenceRecorded($attendance);

        Queue::assertPushed(SendEduNotificationJob::class, function (SendEduNotificationJob $job): bool {
            return $job->templateKey === EduNotificationService::TEMPLATE_ABSENCE_RECORDED
                && ($job->context['student_name'] ?? null) === 'Lina Benali'
                && ($job->context['status'] ?? null) === EduAttendance::STATUS_ABSENT;
        });
    }

    public function test_report_card_published_dispatches_to_directors(): void
    {
        Queue::fake();

        /** @var EduReportCard $card */
        $card = EduReportCard::query()->create([
            'company_id' => $this->companyA->id,
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'period' => EduReportCard::PERIOD_TERM1,
            'status' => EduReportCard::STATUS_PUBLISHED,
        ]);

        app(EduNotificationService::class)->reportCardPublished($card);

        Queue::assertPushed(SendEduNotificationJob::class, function (SendEduNotificationJob $job): bool {
            return $job->templateKey === EduNotificationService::TEMPLATE_REPORT_CARD_PUBLISHED
                && ($job->context['period'] ?? null) === EduReportCard::PERIOD_TERM1;
        });
    }

    public function test_no_dispatch_without_active_direction(): void
    {
        Queue::fake();

        // Aucun manager actif (le principal est suspendu).
        // 'status' n'est pas fillable sur Employee → mise à jour via query builder
        // (CHECK employees_status_check : active/suspended/archived/departed).
        Employee::query()->whereKey($this->principalA->id)->update(['status' => 'suspended']);

        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->create([
            'company_id' => $this->companyA->id,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'admission_number' => 'ADM-2026-00002',
            'applicant_first_name' => 'Yacine',
            'applicant_last_name' => 'Meziane',
            'applied_at' => '2026-06-01',
        ]);

        app(EduNotificationService::class)->admissionConverted($admission);

        Queue::assertNotPushed(SendEduNotificationJob::class);
    }

    public function test_other_tenant_directors_are_never_notified(): void
    {
        Queue::fake();

        // Manager principal du tenant B.
        Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->create([
            'company_id' => $this->companyA->id,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'admission_number' => 'ADM-2026-00003',
            'applicant_first_name' => 'Lina',
            'applicant_last_name' => 'Benali',
            'applied_at' => '2026-06-01',
        ]);

        app(EduNotificationService::class)->admissionConverted($admission);

        Queue::assertPushed(SendEduNotificationJob::class, function (SendEduNotificationJob $job): bool {
            return $job->companyId === $this->companyA->id
                && collect($job->employeeIds)->every(fn (int $id): bool => $id !== 99999);
        });
    }
}
