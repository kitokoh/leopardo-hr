<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #6544 — Coordonnées bancaires de l'entreprise (IBAN/BIC) réservées aux
 * managers : un employé lambda ne doit pas pouvoir lire /company/banking.
 */
class CompanyBankingControllerTest extends TestCase
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

    public function test_employee_cannot_read_company_banking(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/company/banking')
            ->assertStatus(403);
    }

    public function test_manager_reads_company_banking(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/company/banking')
            ->assertOk()
            ->assertJsonStructure(['data' => ['company_iban', 'company_bic', 'sepa_ready']]);
    }

    public function test_comptable_reads_company_banking(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create();
        /** @var \App\Core\Auth\Domain\Models\Employee $comptable */
        $comptable = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
        ]);

        $this->actingAs($comptable, 'sanctum')
            ->getJson('/api/v1/company/banking')
            ->assertOk();
    }
}
