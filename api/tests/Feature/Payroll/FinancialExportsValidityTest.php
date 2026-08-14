<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Infrastructure\Exports\PayrollAccountingExportService;
use App\Modules\Payroll\Infrastructure\Services\BankExportGenerator;
use App\Modules\Payroll\Infrastructure\Services\PayrollJournalGenerator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2223 [PAYROLL][P1] — exports financiers invalides.
 *
 * 1. SEPA : plus de placeholder / UNKNOWN — IBAN/BIC émetteur depuis le
 *    profil entreprise, employés sans IBAN exclus.
 * 2. Journal CSV : les montants négatifs restent des nombres (pas de ').
 * 3. Accounting export : neutralisation CSV des champs texte + seuls les
 *    bulletins validés exportés.
 */
class FinancialExportsValidityTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'FR',
            'currency' => 'EUR',
            'metadata' => [
                'iban' => 'FR7630006000011234567890189',
                'bic' => 'AGRIFRPP',
            ],
        ]);
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    private function makeRun(): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'FR',
            'status' => PayrollRun::STATUS_VALIDATED,
        ]);

        return $run;
    }

    private function addSlip(PayrollRun $run, Employee $employee, float $gross, float $net, string $status = 'validated'): PaySlip
    {
        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => $gross,
            'total_deductions' => round($gross - $net, 2),
            'net_salary' => $net,
            'employer_contributions' => 0,
            'total_cost' => $gross,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'status' => $status,
        ]);

        return $slip;
    }

    public function test_sepa_uses_company_iban_bic_and_skips_missing_employee_iban(): void
    {
        $run = $this->makeRun();
        /** @var Employee $emp1 */
        $emp1 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'iban' => 'FR7630006000011234567890189',
        ]);
        /** @var Employee $emp2 */
        $emp2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Sans',
            'last_name' => 'Iban',
            'iban' => null,
        ]);
        $this->addSlip($run, $emp1, 3000.0, 2400.0);
        $this->addSlip($run, $emp2, 2000.0, 1600.0);

        $xml = (new BankExportGenerator)->generate($run, 'sepa_xml');

        // IBAN/BIC émetteur depuis le profil entreprise — aucun placeholder.
        $this->assertStringContainsString('<IBAN>FR7630006000011234567890189</IBAN></Id></DbtrAcct>', $xml);
        $this->assertStringContainsString('<BIC>AGRIFRPP</BIC>', $xml);
        $this->assertStringNotContainsString('PLACEHOLDER', $xml);
        $this->assertStringNotContainsString('UNKNOWN', $xml);
        // Seul l'employé avec IBAN est dans le fichier.
        $this->assertStringContainsString('SAL-'.$run->id.'-'.$emp1->id, $xml);
        $this->assertStringNotContainsString('SAL-'.$run->id.'-'.$emp2->id, $xml);
        $this->assertStringContainsString('<NbOfTxs>1</NbOfTxs>', $xml);
    }

    public function test_sepa_blocked_when_company_iban_missing(): void
    {
        $run = $this->makeRun();
        $this->company->update(['metadata' => []]);
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'iban' => 'FR7630006000011234567890189',
        ]);
        $this->addSlip($run, $emp, 3000.0, 2400.0);

        $this->expectException(\RuntimeException::class);
        (new BankExportGenerator)->generate($run, 'sepa_xml');
    }

    public function test_journal_csv_keeps_negative_amounts_as_numbers(): void
    {
        $run = $this->makeRun();
        /** @var Employee $emp */
        $emp = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Neg',
            'last_name' => 'atif',
            'matricule' => '=FORMULA',
        ]);
        $slip = $this->addSlip($run, $emp, -500.0, -400.0);
        // Ligne de régularisation négative (bulletins de correction).
        PaySlipLine::create([
            'pay_slip_id' => $slip->id,
            'name' => 'Cotisations salariales',
            'type' => 'deduction',
            'amount' => -100.0,
        ]);

        $csv = (new PayrollJournalGenerator)->generate($run);

        // Montant négatif = nombre, pas de préfixe apostrophe.
        $this->assertStringContainsString('"-500.00"', $csv);
        $this->assertStringContainsString('"-400.00"', $csv);
        $this->assertStringContainsString('"-100.00"', $csv);
        $this->assertStringNotContainsString('"\'', $csv);
        // Le champ texte contrôlé par l'employé reste neutralisé.
        $this->assertStringContainsString('"\'=FORMULA"', $csv);
    }

    public function test_accounting_export_neutralizes_text_and_exports_only_validated(): void
    {
        $run = $this->makeRun();
        /** @var Employee $empValid */
        $empValid = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => '=CMD()|calc',
            'last_name' => 'Injection',
            'matricule' => '=1+1',
        ]);
        /** @var Employee $empDraft */
        $empDraft = Employee::factory()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Draft',
            'last_name' => 'Slip',
        ]);
        $this->addSlip($run, $empValid, 3000.0, 2400.0, 'validated');
        $this->addSlip($run, $empDraft, 3000.0, 2400.0, 'draft');

        $service = new PayrollAccountingExportService;
        $closure = $service->generateCsvClosure($run);

        ob_start();
        $closure();
        $csv = ob_get_clean();

        // Les champs texte commençant par = sont neutralisés.
        $this->assertStringContainsString("'=CMD()|calc", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        // Seul le bulletin validé est exporté (le draft est exclu).
        $this->assertStringContainsString('Injection', $csv);
        $this->assertStringNotContainsString('Draft', $csv);
    }
}
