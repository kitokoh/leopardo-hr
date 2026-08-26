<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5613 — coordonnées bancaires entreprise (débiteur SEPA).
 *
 * L'export SEPA exige companies.metadata.company_iban (#2198) mais aucun
 * endpoint ne permettait de le configurer. GET/PATCH /company/bank-details :
 * RBAC principal/rh, validation IBAN (ou RIB DZ 20 chiffres si pays DZ) +
 * BIC SWIFT, normalisation (uppercase, espaces retirés), isolation tenant.
 */
class CompanyBankDetailsControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_manager_updates_company_bank_details(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->actingAs($manager, 'sanctum')
            ->patch('/api/v1/company/bank-details', [
                'company_iban' => 'FR76 3000 6000 0112 3456 7890 189',
                'company_bic' => 'BNPAFRPPXXX',
            ])
            ->assertOk()
            ->assertJsonPath('data.iban', 'FR7630006000011234567890189')
            ->assertJsonPath('data.bic', 'BNPAFRPPXXX');

        $metadata = Company::query()->findOrFail($company->id)->metadata;
        $this->assertSame('FR7630006000011234567890189', $metadata['company_iban']);
        $this->assertSame('BNPAFRPPXXX', $metadata['company_bic']);
    }

    public function test_employee_can_read_but_cannot_update_bank_details(): void
    {
        $company = Company::factory()->create([
            'metadata' => ['company_iban' => 'FR7630006000011234567890189'],
        ]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->actingAs($employee, 'sanctum')
            ->get('/api/v1/company/bank-details')
            ->assertOk()
            ->assertJsonPath('data.iban', 'FR7630006000011234567890189');

        $this->actingAs($employee, 'sanctum')
            ->patch('/api/v1/company/bank-details', ['company_iban' => 'FR7630006000011234567890190'])
            ->assertForbidden();
    }

    public function test_invalid_iban_is_rejected(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->actingAs($manager, 'sanctum')
            ->patch('/api/v1/company/bank-details', ['company_iban' => 'NOTANIBAN'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_iban');
    }

    public function test_dz_company_accepts_20_digit_rib(): void
    {
        $company = Company::factory()->create(['country' => 'DZ']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->actingAs($manager, 'sanctum')
            ->patch('/api/v1/company/bank-details', [
                'company_iban' => '00123456789012345678', // RIB DZ 20 chiffres
            ])
            ->assertOk()
            ->assertJsonPath('data.iban', '00123456789012345678');
    }

    public function test_non_dz_company_rejects_20_digit_rib(): void
    {
        $company = Company::factory()->create(['country' => 'FR']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->actingAs($manager, 'sanctum')
            ->patch('/api/v1/company/bank-details', [
                'company_iban' => '00123456789012345678',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_iban');
    }

    public function test_invalid_bic_is_rejected(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->actingAs($manager, 'sanctum')
            ->patch('/api/v1/company/bank-details', [
                'company_bic' => '123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_bic');
    }

    public function test_clearing_iban_removes_the_key(): void
    {
        $company = Company::factory()->create([
            'metadata' => ['company_iban' => 'FR7630006000011234567890189', 'company_bic' => 'BNPAFRPP'],
        ]);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->actingAs($manager, 'sanctum')
            ->patch('/api/v1/company/bank-details', ['company_iban' => null])
            ->assertOk()
            ->assertJsonPath('data.iban', null);

        $metadata = Company::query()->findOrFail($company->id)->metadata;
        $this->assertArrayNotHasKey('company_iban', $metadata);
        $this->assertSame('BNPAFRPP', $metadata['company_bic']);
    }
}
