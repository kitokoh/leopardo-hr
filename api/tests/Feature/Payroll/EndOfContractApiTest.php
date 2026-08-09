<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-08 (#1538) : fin de contrat via l'API.
 *
 * GET /employees/{employee}/end-of-contract           → solde de tout compte
 * GET /employees/{employee}/certificate-of-employment → certificat PDF
 */
class EndOfContractApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_start' => '2023-07-01',
            'salary_base' => 60000,
            'position' => 'Développeur',
        ]);
        $this->employee = $employee;

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Cadre moyen DZ',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);
    }

    public function test_settlement_returns_breakdown(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/employees/{$this->employee->id}/end-of-contract")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'employee_id',
                    'end_date',
                    'years_of_service',
                    'monthly_base',
                    'working_days',
                    'prorated_days',
                    'unpaid_leave_days',
                    'reference_gross_12_months',
                    'breakdown' => ['prorated_pay', 'leave_indemnity', 'notice_pay', 'severance', 'total'],
                ],
            ])
            ->assertJsonPath('data.monthly_base', 60000)
            ->assertJsonPath('data.breakdown.severance', 60000 * 3); // 3 ans d'ancienneté × 1 mois
    }

    public function test_certificate_pdf_download(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson("/api/v1/employees/{$this->employee->id}/certificate-of-employment")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_employee_cannot_read_end_of_contract(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson("/api/v1/employees/{$this->employee->id}/end-of-contract")->assertStatus(403);
        $this->getJson("/api/v1/employees/{$this->employee->id}/certificate-of-employment")->assertStatus(403);
    }

    public function test_cross_tenant_end_of_contract_is_forbidden(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $other->id]);

        Sanctum::actingAs($otherManager);

        $this->getJson("/api/v1/employees/{$this->employee->id}/end-of-contract")->assertStatus(404);
        $this->getJson("/api/v1/employees/{$this->employee->id}/certificate-of-employment")->assertStatus(404);
    }
}
