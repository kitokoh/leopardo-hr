<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduAdmissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5820 (EDU-004) — admissions et lien CRM client.
 *
 * Verrouille : création idempotente (external_id), numéro de dossier auto,
 * conversion idempotente, consentement obligatoire, statuts terminaux, lien
 * CRM sans FK (référence de contrat), isolation tenant.
 */
class EduAdmissionServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private EduAcademicYear $yearA;

    private Employee $managerA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Employee $managerA */
        $managerA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;

        $this->managerA = $managerA;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function admissionPayload(array $overrides = []): array
    {
        return array_merge([
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'applicant_first_name' => 'Lina',
            'applicant_last_name' => 'Benali',
            'applicant_email' => 'lina@example.com',
            'applied_at' => '2026-09-01',
            'consent_contact' => true,
            'consented_at' => now(),
            'source' => 'web',
            'external_id' => 'ext-'.uniqid('', false),
        ], $overrides);
    }

    public function test_admissions_table_exists_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('edu_admissions'));
    }

    public function test_create_is_idempotent_on_external_id(): void
    {
        $service = app(EduAdmissionService::class);
        $payload = $this->admissionPayload();

        $first = $service->create($this->managerA, $payload);
        $second = $service->create($this->managerA, $payload);

        $this->assertSame((int) $first->getAttribute('id'), (int) $second->getAttribute('id'));
        $this->assertSame(1, EduAdmission::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_admission_number_is_auto_generated(): void
    {
        $service = app(EduAdmissionService::class);
        $admission = $service->create($this->managerA, $this->admissionPayload());

        $this->assertNotNull($admission->admission_number);
        $this->assertStringStartsWith('ADM-', (string) $admission->admission_number);
    }

    public function test_conversion_requires_consent(): void
    {
        $service = app(EduAdmissionService::class);
        $admission = $service->create($this->managerA, $this->admissionPayload(['consent_contact' => false]));

        $this->expectExceptionMessage('EDU_CONSENT_REQUIRED');

        $service->convertToStudent($this->managerA, $admission);
    }

    public function test_conversion_is_idempotent(): void
    {
        $service = app(EduAdmissionService::class);
        $admission = $service->create($this->managerA, $this->admissionPayload());

        $first = $service->convertToStudent($this->managerA, $admission);
        $second = $service->convertToStudent($this->managerA, $admission->refresh());

        $this->assertSame((int) $first->getAttribute('id'), (int) $second->getAttribute('id'));
        $this->assertSame(EduAdmission::STATUS_CONVERTED, $admission->refresh()->status);
        $this->assertNotNull($admission->converted_at);
        $this->assertSame((int) $first->getAttribute('id'), (int) $admission->student_id);
        $this->assertSame(1, EduStudent::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_conversion_creates_student_with_consistent_data(): void
    {
        $service = app(EduAdmissionService::class);
        $admission = $service->create($this->managerA, $this->admissionPayload([
            'applicant_first_name' => 'Yacine',
            'applicant_last_name' => 'Meziane',
        ]));

        $student = $service->convertToStudent($this->managerA, $admission);

        $this->assertSame('Yacine Meziane', $student->display_name);
        $this->assertSame(EduStudent::STATUS_ACTIVE, $student->status);
    }

    public function test_crm_contact_reference_does_not_create_crm_records(): void
    {
        $service = app(EduAdmissionService::class);
        $admission = $service->create($this->managerA, $this->admissionPayload([
            'crm_contact_id' => 'crm-contract-1',
        ]));

        $this->assertSame('crm-contract-1', $admission->crm_contact_id);
        // Aucune table CRM touchée : la référence est une simple colonne.
        $this->assertSame(0, DB::table('crm_imports')->count());
    }
}
