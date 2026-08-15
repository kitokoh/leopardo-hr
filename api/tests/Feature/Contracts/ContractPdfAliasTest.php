<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Contract;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2226 — la Web App appelle GET /contracts/{id}/pdf mais seule
 * GET /contracts/{contract}/generate-pdf existait → bouton PDF = 404.
 * L'alias /pdf doit servir le même PDF (200, Content-Type application/pdf)
 * et rester isolé par tenant (404 cross-tenant).
 */
class ContractPdfAliasTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_pdf_alias_returns_pdf_for_manager_of_own_tenant(): void
    {
        [$company, $manager, $employee] = $this->actors();

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'reference' => 'CTR-ALIAS-001',
            'start_date' => '2026-01-01',
            'job_title' => 'Développeur full-stack',
            'base_salary' => 250000,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'work_hours_per_week' => 40,
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/contracts/'.$contract->id.'/pdf');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="contract_'.$contract->id.'.pdf"');

        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_pdf_alias_is_404_for_cross_tenant_contract(): void
    {
        [$companyA, $managerA, $employeeA] = $this->actors('A');
        [, $managerB] = $this->actors('B');

        $contract = Contract::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'contract_type' => 'cdi',
            'reference' => 'CTR-ALIAS-002',
            'start_date' => '2026-02-01',
            'job_title' => 'Comptable',
            'base_salary' => 180000,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'status' => 'active',
        ]);

        // Manager du tenant B ne doit pas pouvoir générer le PDF d'un
        // contrat du tenant A (ni via l'alias /pdf ni via /generate-pdf).
        Sanctum::actingAs($managerB);

        $this->getJson('/api/v1/contracts/'.$contract->id.'/pdf')->assertNotFound();
        $this->getJson('/api/v1/contracts/'.$contract->id.'/generate-pdf')->assertNotFound();
    }

    public function test_employee_can_download_own_contract_pdf_but_not_others(): void
    {
        [$company, $manager, $employee] = $this->actors();

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdd',
            'reference' => 'CTR-ALIAS-003',
            'start_date' => '2026-03-01',
            'end_date' => '2026-08-31',
            'job_title' => 'Assistant RH',
            'base_salary' => 120000,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'status' => 'active',
        ]);

        // L'employé titulaire du contrat peut télécharger son propre PDF…
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/contracts/'.$contract->id.'/pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        // …mais pas le PDF d'un contrat qui n'est pas le sien (403).
        [, , $otherEmployee] = $this->actors('C');
        $otherContract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'contract_type' => 'cdi',
            'reference' => 'CTR-ALIAS-004',
            'start_date' => '2026-04-01',
            'job_title' => 'Chargé clientèle',
            'base_salary' => 140000,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/contracts/'.$otherContract->id.'/pdf')->assertForbidden();
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function actors(string $suffix = 'A'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Contract Co '.$suffix,
            'slug' => 'contract-co-'.strtolower($suffix),
        ]);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create([
            'company_id' => $company->id,
            'first_name' => 'Manager',
            'last_name' => $suffix,
            'email' => 'manager-'.strtolower($suffix).'@contract.test',
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Employee',
            'last_name' => $suffix,
            'email' => 'employee-'.strtolower($suffix).'@contract.test',
        ]);

        return [$company, $manager, $employee];
    }
}
