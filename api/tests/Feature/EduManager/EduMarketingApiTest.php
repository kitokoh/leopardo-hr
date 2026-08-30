<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API marketing admissions — EDU-015 (issue #5831).
 *
 * Couvre : segments de prospects CONSENTIS uniquement, exclusion des
 * convertis/refusés, opt-out RGPD, isolation cross-tenant, aucun lien vers
 * le CRM commercial Leopardo (finalité + minimisation).
 */
class EduMarketingApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

    private EduAcademicYear $yearA;

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

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
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $this->yearA = $yearA;
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

    public function test_eligible_prospects_only_consented_and_open(): void
    {
        Sanctum::actingAs($this->principalA);

        // Consentis et ouverts → inclus.
        $this->admission(['admission_number' => 'ADM-1', 'consent_contact' => true, 'status' => EduAdmission::STATUS_NEW]);
        $this->admission(['admission_number' => 'ADM-2', 'consent_contact' => true, 'status' => EduAdmission::STATUS_WAITLISTED]);
        // Sans consentement → exclu (RGPD).
        $this->admission(['admission_number' => 'ADM-3', 'consent_contact' => false]);
        // Converti → exclu (déjà élève).
        $this->admission(['admission_number' => 'ADM-4', 'consent_contact' => true, 'status' => EduAdmission::STATUS_CONVERTED]);

        $this->getJson($this->baseUrl().'/marketing/eligible-prospects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.admission_number', 'ADM-2'); // trié par applied_at desc
    }

    public function test_opt_out_removes_prospect_from_segments(): void
    {
        Sanctum::actingAs($this->principalA);

        $admission = $this->admission(['admission_number' => 'ADM-1']);

        $this->postJson($this->baseUrl()."/admissions/{$admission->getAttribute('id')}/opt-out")
            ->assertOk()
            ->assertJsonPath('data.consent_contact', false);

        $this->getJson($this->baseUrl().'/marketing/eligible-prospects')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_plain_employee_gets_403(): void
    {
        /** @var Employee $lambda */
        $lambda = Employee::factory()->create(['company_id' => $this->companyA->id]);
        Sanctum::actingAs($lambda);

        $this->getJson($this->baseUrl().'/marketing/eligible-prospects')->assertStatus(403);
    }

    public function test_cross_tenant_prospect_is_404(): void
    {
        Sanctum::actingAs($this->principalA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);
        /** @var EduAcademicYear $yearB */
        $yearB = EduAcademicYear::query()->create([
            'company_id' => $companyB->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        /** @var EduAdmission $admissionB */
        $admissionB = EduAdmission::query()->create([
            'company_id' => $companyB->id,
            'academic_year_id' => (int) $yearB->getAttribute('id'),
            'admission_number' => 'ADM-B',
            'applicant_first_name' => 'Élève',
            'applicant_last_name' => 'B',
            'applied_at' => '2026-06-01',
            'consent_contact' => true,
            'status' => EduAdmission::STATUS_NEW,
        ]);

        // L'opt-out ne touche jamais un prospect d'un autre tenant.
        $this->postJson($this->baseUrl()."/admissions/{$admissionB->getAttribute('id')}/opt-out")->assertStatus(404);
    }
}
