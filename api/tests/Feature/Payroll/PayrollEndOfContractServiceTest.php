<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\EndOfContractService;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Spec S-4 (#1664) — Couverture Payroll ≥ 80 % : `EndOfContractService`
 * (solde de tout compte + certificat de travail, F-08) — calculs réels
 * employé/entreprise (salaire, ancienneté, congés non pris, référence 12 mois).
 */
class PayrollEndOfContractServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_start' => '2023-07-01',
            'contract_end' => '2026-06-30',
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

    public function test_settlement_computes_breakdown_with_real_employee_data(): void
    {
        $service = new EndOfContractService();

        $settlement = $service->settlement($this->employee);

        $this->assertSame($this->employee->id, $settlement['employee_id']);
        $this->assertSame('2026-06-30', $settlement['end_date']);
        $this->assertSame(60000.0, $settlement['monthly_base']);
        $this->assertSame(22.0, $settlement['working_days']);
        $this->assertSame(3.0, $settlement['years_of_service']);
        $this->assertSame(0.0, $settlement['unpaid_leave_days']);
        $this->assertSame(720000.0, $settlement['reference_gross_12_months']); // fallback base × 12
        $this->assertArrayHasKey('total', $settlement['breakdown']);
        $this->assertGreaterThan(0.0, $settlement['breakdown']['total']);
    }

    public function test_settlement_uses_explicit_end_date(): void
    {
        $service = new EndOfContractService();

        $settlement = $service->settlement($this->employee, now()->parse('2026-12-31'));

        $this->assertSame('2026-12-31', $settlement['end_date']);
        $this->assertSame(3.5, $settlement['years_of_service']);
    }

    public function test_monthly_base_falls_back_to_active_salary_structure(): void
    {
        /** @var Employee $noBase */
        $noBase = Employee::factory()->create([
            'company_id' => $this->company->id,
            'contract_start' => '2024-01-01',
            'salary_base' => null,
        ]);

        $settlement = (new EndOfContractService())->settlement($noBase);

        $this->assertSame(60000.0, $settlement['monthly_base']);
    }

    public function test_unpaid_leave_days_sums_paid_leave_balance(): void
    {
        /** @var AbsenceType $paidType */
        $paidType = AbsenceType::create([
            'company_id' => $this->company->id,
            'name' => 'Congé payé',
            'code' => 'PAID',
            'is_paid' => true,
        ]);
        /** @var AbsenceType $unpaidType */
        $unpaidType = AbsenceType::create([
            'company_id' => $this->company->id,
            'name' => 'Sans solde',
            'code' => 'UNPAID',
            'is_paid' => false,
        ]);

        LeaveBalance::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'year' => 2026,
            'absence_type_id' => $paidType->id,
            'balance' => 12.5,
        ]);
        LeaveBalance::create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'year' => 2026,
            'absence_type_id' => $unpaidType->id,
            'balance' => 99.0, // non pris en compte : type non payé
        ]);

        $settlement = (new EndOfContractService())->settlement($this->employee);

        $this->assertSame(12.5, $settlement['unpaid_leave_days']);
    }

    public function test_reference_gross_uses_validated_slips_of_last_12_months(): void
    {
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);
        PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'gross_salary' => 70000,
            'net_salary' => 55000,
            'status' => 'validated',
        ]);

        $settlement = (new EndOfContractService())->settlement($this->employee);

        // 1 bulletin validé (70 000) > 0 → référence réelle, pas le fallback 12 × base.
        $this->assertSame(70000.0, $settlement['reference_gross_12_months']);
    }

    public function test_certificate_data_exposes_company_and_months_of_service(): void
    {
        $data = (new EndOfContractService())->certificateData($this->employee);

        $this->assertSame($this->employee->id, $data['employee']->id);
        $this->assertSame($this->company->id, $data['company']->id);
        $this->assertSame(36, $data['months_of_service']);
        $this->assertSame($this->employee->id, $data['settlement']['employee_id']);
    }

    public function test_no_contract_start_yields_zero_tenure(): void
    {
        /** @var Employee $noStart */
        $noStart = Employee::factory()->create([
            'company_id' => $this->company->id,
            'contract_start' => null,
            'contract_end' => '2026-06-30',
            'salary_base' => 60000,
        ]);

        $settlement = (new EndOfContractService())->settlement($noStart);

        $this->assertSame(0.0, $settlement['years_of_service']);
    }
}
