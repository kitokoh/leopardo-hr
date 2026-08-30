<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Infrastructure\Services\EduMarketingService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5831 (EDU-015) — marketing admissions (segments consentis).
 *
 * Verrouille : seuls les prospects CONSENTIS aux statuts ouverts sont
 * exposés, fenêtre de dates respectée, crm_contact_id transmis (contrat),
 * isolation tenant — jamais de prospect sans consentement.
 */
class EduMarketingServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private EduAcademicYear $yearA;

    private Company $companyB;

    private Employee $principalA;

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
        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;
        $this->principalA = $principalA;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function admission(array $overrides = []): EduAdmission
    {
        /** @var EduAdmission $admission */
        $admission = EduAdmission::query()->create(array_merge([
            'company_id' => $this->companyA->id,
            'academic_year_id' => (int) $this->yearA->getAttribute('id'),
            'admission_number' => 'ADM-'.uniqid('', false),
            'applicant_first_name' => 'Lina',
            'applicant_last_name' => 'Benali',
            'applied_at' => '2026-06-01',
            'consent_contact' => true,
            'status' => EduAdmission::STATUS_NEW,
        ], $overrides));

        return $admission;
    }

    public function test_only_consented_open_admissions_are_eligible(): void
    {
        // Consentie + ouverte → éligible.
        $this->admission(['crm_contact_id' => 'crm-1']);
        // Sans consentement → exclue.
        $this->admission(['consent_contact' => false]);
        // Convertie (statut terminal) → exclue des campagnes.
        $this->admission(['status' => EduAdmission::STATUS_CONVERTED, 'consent_contact' => true]);

        $eligible = app(EduMarketingService::class)->marketingEligible($this->principalA, null, null);

        $this->assertCount(1, $eligible);
        $this->assertSame('crm-1', $eligible[0]['crm_contact_id']);
    }

    public function test_date_window_is_respected(): void
    {
        $this->admission(['admission_number' => 'ADM-JUIN', 'applied_at' => '2026-06-01']);
        $this->admission(['admission_number' => 'ADM-AOUT', 'applied_at' => '2026-08-01']);

        $eligible = app(EduMarketingService::class)
            ->marketingEligible($this->principalA, '2026-06-01', '2026-06-30');

        $this->assertCount(1, $eligible);
        $this->assertSame('ADM-JUIN', $eligible[0]['admission_number']);
    }

    public function test_tenant_isolation(): void
    {
        // Admission du tenant B (année scolaire du tenant B).
        /** @var EduAcademicYear $yearB */
        $yearB = EduAcademicYear::query()->create([
            'company_id' => $this->companyB->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        EduAdmission::query()->create([
            'company_id' => $this->companyB->id,
            'academic_year_id' => (int) $yearB->getAttribute('id'),
            'admission_number' => 'ADM-B',
            'applicant_first_name' => 'Élève',
            'applicant_last_name' => 'B',
            'applied_at' => '2026-06-01',
            'consent_contact' => true,
            'status' => EduAdmission::STATUS_NEW,
        ]);

        $eligible = app(EduMarketingService::class)->marketingEligible($this->principalA, null, null);

        $this->assertCount(0, $eligible);
    }
}
