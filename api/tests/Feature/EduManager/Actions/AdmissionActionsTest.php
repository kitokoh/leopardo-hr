<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Actions\ConvertEduAdmissionToStudentAction;
use App\Modules\EduManager\Application\Actions\CreateEduAdmissionAction;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-024a (#8212) — Actions du cas d'usage « admission » : création
 * idempotente et conversion en élève (consentement obligatoire, idempotente).
 */
class AdmissionActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

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
    }

    public function test_create_admission_attaches_session_tenant(): void
    {
        $admission = app(CreateEduAdmissionAction::class)->execute($this->principalA, [
            'academic_year_id' => $this->yearA->getKey(),
            'applicant_first_name' => 'Nour',
            'applicant_last_name' => 'Haddad',
            'consent_contact' => true,
            'source' => 'web',
        ]);

        $this->assertSame($this->companyA->getKey(), $admission->getAttribute('company_id'));
        $this->assertNotEmpty($admission->admission_number);
    }

    public function test_convert_admission_creates_student_and_marks_converted(): void
    {
        $admission = app(CreateEduAdmissionAction::class)->execute($this->principalA, [
            'academic_year_id' => $this->yearA->getKey(),
            'applicant_first_name' => 'Nour',
            'applicant_last_name' => 'Haddad',
            'consent_contact' => true,
            'source' => 'web',
        ]);

        $student = app(ConvertEduAdmissionToStudentAction::class)->execute($this->principalA, $admission);

        $this->assertSame($this->companyA->getKey(), $student->getAttribute('company_id'));
        $this->assertSame('Nour Haddad', $student->display_name);

        /** @var EduAdmission $refreshed */
        $refreshed = $admission->refresh();
        $this->assertSame(EduAdmission::STATUS_CONVERTED, $refreshed->status);
        $this->assertSame($student->getKey(), $refreshed->getAttribute('student_id'));
        $this->assertNotNull($refreshed->converted_at);
    }

    public function test_convert_admission_is_idempotent_on_replay(): void
    {
        $admission = app(CreateEduAdmissionAction::class)->execute($this->principalA, [
            'academic_year_id' => $this->yearA->getKey(),
            'applicant_first_name' => 'Nour',
            'applicant_last_name' => 'Haddad',
            'consent_contact' => true,
        ]);

        $first = app(ConvertEduAdmissionToStudentAction::class)->execute($this->principalA, $admission);
        $second = app(ConvertEduAdmissionToStudentAction::class)->execute($this->principalA, $admission->refresh());

        // Rejeu → le même élève, aucun doublon.
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, EduStudent::query()
            ->where('company_id', $this->companyA->id)
            ->count());
    }
}
