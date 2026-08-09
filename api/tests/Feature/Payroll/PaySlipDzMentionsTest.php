<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-09 (#1539) : mentions légales DZ (NIF, RC, n° CNAS
 * employeur, ID.Nat via company.metadata) et cumuls annuels sur le bulletin.
 */
class PaySlipDzMentionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeSlip(Company $company, Employee $employee, string $period = '2026-07'): PaySlip
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => "{$period}-01",
            'period_end' => "{$period}-31",
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => "{$period}-01",
            'period_end' => "{$period}-31",
            'gross_salary' => 60000,
            'total_deductions' => 12442,
            'net_salary' => 47558,
            'status' => 'validated',
        ]);

        return $slip;
    }

    public function test_pdf_contains_company_legal_identifiers(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => [
                'legal_nif' => '099916012345678',
                'legal_rc' => '16/00-1234567B23',
                'legal_cnas_employer' => '1234567890',
                'legal_idnat' => '1099-1234567-8',
            ],
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $slip = $this->makeSlip($company, $employee);
        $binary = app(PaySlipPdfGenerator::class)->generate($slip);

        $text = $this->pdfText($binary);

        $this->assertStringContainsString('NIF', $text);
        $this->assertStringContainsString('099916012345678', $text);
        $this->assertStringContainsString('RC', $text);
        $this->assertStringContainsString('16/00-1234567B23', $text);
        $this->assertStringContainsString('N° CNAS employeur', $text);
        $this->assertStringContainsString('ID.Nat', $text);
    }

    public function test_pdf_contains_annual_cumuls(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        // Janvier (cumul = ce bulletin) puis juillet (cumul = 2 bulletins).
        $this->makeSlip($company, $employee, '2026-01');
        $july = $this->makeSlip($company, $employee, '2026-07');

        $binary = app(PaySlipPdfGenerator::class)->generate($july);
        $text = $this->pdfText($binary);

        // Cumuls annuels : brut 120 000, retenues 24 884, net 95 116.
        $this->assertStringContainsString('120 000,00', $text);
        $this->assertStringContainsString('24 884,00', $text);
        $this->assertStringContainsString('95 116,00', $text);
    }

    private function pdfText(string $binary): string
    {
        // Extraction de texte basique d'un PDF généré par dompdf : les chaînes
        // sont stockées entre parenthèses dans les flux de contenu.
        $text = '';
        $inParen = false;
        $buf = '';
        foreach (str_split($binary) as $ch) {
            if ($inParen) {
                if ($ch === ')') {
                    $text .= $buf.' ';
                    $buf = '';
                    $inParen = false;
                } elseif ($ch === '(') {
                    $buf .= '(';
                } else {
                    $buf .= $ch;
                }
            } elseif ($ch === '(') {
                $inParen = true;
                $buf = '';
            }
        }

        return $text;
    }
}
