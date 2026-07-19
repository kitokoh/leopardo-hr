<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-I18N-005 — the pay slip PDF must render in the employee's preferred
 * language, RTL for Arabic, instead of the hardcoded French previously
 * baked into api/resources/views/pdf/payslip.blade.php.
 */
class PaySlipPdfLocaleTest extends TestCase
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

    public function test_payslip_pdf_renders_in_arabic_rtl_for_employee_preference(): void
    {
        $company = Company::factory()->create(['language' => 'fr']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'ar',
        ]);

        [, $slip] = $this->payrollSlip($company, $employee);

        $binary = app(PaySlipPdfGenerator::class)->generate($slip);

        $this->assertNotEmpty($binary);
        $this->assertSame('ar', app()->getLocale());
    }

    public function test_payslip_pdf_falls_back_to_company_language_when_employee_has_none(): void
    {
        $company = Company::factory()->create(['language' => 'en']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => null,
        ]);

        [, $slip] = $this->payrollSlip($company, $employee);

        app(PaySlipPdfGenerator::class)->generate($slip);

        $this->assertSame('en', app()->getLocale());
    }

    /**
     * @return array{0: PayrollRun, 1: PaySlip}
     */
    private function payrollSlip(Company $company, Employee $employee): array
    {
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'validated',
            'employee_count' => 1,
            'total_gross' => 120000,
            'total_deductions' => 22000,
            'total_net' => 98000,
        ]);

        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 22000,
            'net_salary' => 98000,
            'employer_contributions' => 31200,
            'total_cost' => 151200,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        PaySlipLine::query()->create([
            'pay_slip_id' => $slip->id,
            'name' => 'Salaire de base',
            'type' => 'earning',
            'base_amount' => 120000,
            'rate' => 1,
            'amount' => 120000,
            'order' => 1,
        ]);

        return [$run, $slip];
    }
}
