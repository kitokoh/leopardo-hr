<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Exceptions\DsnConformityException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Infrastructure\Exports\CanadaT4ExportService;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7935 — Feuillets annuels canadiens T4 / RL-1 : agregation annuelle,
 * structure des cases et refus des feuillets non declarables.
 *
 * Les NAS de test sont mathematiquement valides (cle de Luhn) : un NAS
 * quelconque serait rejete par le controle bloquant, ce qui est le but.
 */
class CanadaT4ExportServiceTest extends TestCase
{
    use CreatesMvpSchema;

    /** NAS valide (cle de Luhn calculee). */
    private const VALID_SIN = '046454286';

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

    public function test_t4_aggregates_the_whole_year_not_a_single_run(): void
    {
        $slips = $this->makeYear();

        $xml = (new CanadaT4ExportService)->buildT4($slips, 2026);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'Le XML T4 doit etre bien forme.');
        $root = $dom->documentElement;
        $this->assertNotNull($root, 'Le document T4 doit avoir un element racine.');
        $this->assertSame('T4Declarations', $root->nodeName);
        $this->assertSame('2026', $root->getAttribute('annee'));

        // Deux bulletins de 5 000,00 => case 14 = 10 000,00 (le NET, 7 000,
        // serait faux : le fisc attend le revenu avant retenues).
        $this->assertStringContainsString('<Case14_RevenuEmploi>10000.00</Case14_RevenuEmploi>', $xml);
        $this->assertStringNotContainsString('<Case14_RevenuEmploi>7000.00</Case14_RevenuEmploi>', $xml);

        $this->assertStringContainsString(self::VALID_SIN, $xml);
        $this->assertStringContainsString('<Nom>Tremblay</Nom>', $xml);
        $this->assertStringContainsString('<Prenom>Marie</Prenom>', $xml);
    }

    public function test_t4_carries_the_tax_actually_withheld(): void
    {
        $slips = $this->makeYear();

        $xml = (new CanadaT4ExportService)->buildT4($slips, 2026);

        // 1 500,00 d'impot federal par bulletin x 2.
        $this->assertStringContainsString('<Case22_ImpotFederalRetenu>3000.00</Case22_ImpotFederalRetenu>', $xml);
        // 500,00 de RRQ par bulletin x 2.
        $this->assertStringContainsString('<Case24_RRQ_RPC>1000.00</Case24_RRQ_RPC>', $xml);
    }

    public function test_slips_from_another_year_are_not_merged_into_the_slip(): void
    {
        $slips = $this->makeYear();

        // Un bulletin de 2025 dans le lot : agreger le corromprait 2026.
        $existing = $slips[0];
        $employee = $existing->employee;
        $this->assertNotNull($employee, 'Le bulletin de reference doit porter son employe.');
        $slips[] = $this->makeSlip($employee, $existing->company_id ?? 1, '2025-12-01', 99_000.00);

        $xml = (new CanadaT4ExportService)->buildT4($slips, 2026);

        $this->assertStringContainsString('<Case14_RevenuEmploi>10000.00</Case14_RevenuEmploi>', $xml);
        $this->assertStringNotContainsString('109000.00', $xml, 'Le bulletin 2025 ne doit pas entrer dans le T4 2026.');
    }

    public function test_rl1_is_produced_only_for_quebec_employees(): void
    {
        $slips = $this->makeYear();
        $employeeId = $slips[0]->employee_id;

        $xml = (new CanadaT4ExportService)->buildRl1($slips, 2026, [$employeeId => 'QC']);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
        $root = $dom->documentElement;
        $this->assertNotNull($root, 'Le document RL-1 doit avoir un element racine.');
        $this->assertSame('RL1Declarations', $root->nodeName);
        $this->assertStringContainsString('<CaseA_RevenuEmploi>10000.00</CaseA_RevenuEmploi>', $xml);

        // Un salarie de l'Ontario ne produit AUCUN RL-1 : c'est une
        // declaration a une administration ou il n'est pas enregistre.
        $this->assertSame('', (new CanadaT4ExportService)->buildRl1($slips, 2026, [$employeeId => 'ON']));

        // Sans province fournie, pas de RL-1 non plus — jamais de « QC » par defaut.
        $this->assertSame('', (new CanadaT4ExportService)->buildRl1($slips, 2026));
    }

    public function test_rejects_missing_sin(): void
    {
        $slips = $this->makeYear(sin: null);

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessage('NAS absent');

        (new CanadaT4ExportService)->buildT4($slips, 2026);
    }

    public function test_rejects_invalid_sin(): void
    {
        $slips = $this->makeYear(sin: '123456789');

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessage('NAS invalide');

        (new CanadaT4ExportService)->buildT4($slips, 2026);
    }

    public function test_rejects_a_slip_without_withheld_federal_tax(): void
    {
        // Sans retenue, la case 22 vaut 0 et l'ARC lit « aucun impot retenu » :
        // l'employeur devient redevable de la difference.
        $slips = $this->makeYear(withFederalTax: false);

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessage('impot federal retenu');

        (new CanadaT4ExportService)->buildT4($slips, 2026);
    }

    /**
     * Deux bulletins valides pour la meme annee et le meme salarie.
     *
     * @return list<PaySlip>
     */
    private function makeYear(
        ?string $sin = self::VALID_SIN,
        bool $withFederalTax = true,
    ): array {
        $company = Company::factory()->create(['country' => 'CA']);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Marie',
            'last_name' => 'Tremblay',
            'matricule' => 'CA-001',
            'status' => 'active',
            'national_id' => $sin,
        ]);

        $first = $this->makeSlip($employee, $company->id, '2026-01-01', 5_000.00, $withFederalTax);
        $second = $this->makeSlip($employee, $company->id, '2026-02-01', 5_000.00, $withFederalTax);

        return [$first, $second];
    }

    private function makeSlip(
        Employee $employee,
        int|string $companyId,
        string $periodStart,
        float $gross,
        bool $withFederalTax = true,
    ): PaySlip {
        $run = PayrollRun::query()->create([
            'company_id' => $companyId,
            'country_code' => 'CA',
            'status' => 'validated',
            'period_start' => $periodStart,
            'period_end' => $periodStart,
            'total_gross' => $gross,
            'total_net' => $gross * 0.7,
            'employee_count' => 1,
        ]);

        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $companyId,
            'employee_id' => $employee->id,
            'period_start' => $periodStart,
            'period_end' => $periodStart,
            'gross_salary' => $gross,
            'total_deductions' => $gross * 0.3,
            'net_salary' => $gross * 0.7,
            'employer_contributions' => $gross * 0.2,
            'working_days' => 22.0,
            'actual_days_worked' => 22.0,
            'status' => 'validated',
        ]);

        if ($withFederalTax) {
            $this->line($slip, 'impot_federal', 'Impot federal', -1_500.00);
        }
        $this->line($slip, 'rrq', 'RRQ', -500.00);
        $this->line($slip, 'rqap', 'RQAP', -50.00);

        return $slip->fresh() ?? $slip;
    }

    private function line(PaySlip $slip, string $code, string $name, float $amount): void
    {
        $component = SalaryComponent::query()->create([
            'company_id' => $slip->company_id,
            'name' => $name,
            'code' => $code,
            'type' => 'deduction',
            'amount' => 0,
            'percentage' => 0,
            'is_taxable' => false,
            'is_recurring' => true,
            'order' => 1,
            'active' => true,
        ]);

        PaySlipLine::query()->create([
            'pay_slip_id' => $slip->id,
            'salary_component_id' => $component->id,
            'name' => $name,
            'type' => 'deduction',
            'base_amount' => $slip->gross_salary,
            'amount' => $amount,
            'order' => 1,
        ]);
    }
}
