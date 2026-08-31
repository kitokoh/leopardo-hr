<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6544 (audit surface API M1) — GET /company/banking :
 * l'IBAN/BIC de l'entreprise était exposé à TOUT employé authentifié.
 * Lecture réservée aux rôles financiers (principal/rh/comptable),
 * comme l'écriture (update).
 */
class CompanyBankingRbacTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_plain_employee_is_forbidden_from_company_banking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $company->forceFill([
            'metadata' => ['company_iban' => 'FR7630006000011234567890189', 'company_bic' => 'AGRIFRPP882'],
        ])->save();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/company/banking')
            ->assertForbidden();
    }

    public function test_principal_manager_can_read_company_banking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $company->forceFill([
            'metadata' => ['company_iban' => 'FR7630006000011234567890189', 'company_bic' => 'AGRIFRPP882'],
        ])->save();

        Sanctum::actingAs($principal);

        $this->getJson('/api/v1/company/banking')
            ->assertOk()
            ->assertJsonPath('data.company_iban', 'FR7630006000011234567890189')
            ->assertJsonPath('data.sepa_ready', true);
    }

    public function test_comptable_can_read_company_banking(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        /** @var Employee $comptable */
        $comptable = Employee::factory()->managerComptable()->create(['company_id' => $company->id]);

        Sanctum::actingAs($comptable);

        $this->getJson('/api/v1/company/banking')
            ->assertOk();
    }
}
