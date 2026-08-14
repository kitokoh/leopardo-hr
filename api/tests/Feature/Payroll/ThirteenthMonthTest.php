<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ZONE-INFRA (#1820) — intégration moteur : 13ème mois légal obligatoire et
 * taxe de minimum fiscal (TRIMF) injectés par PayrollCalculator::calculateSlip()
 * quand la règle pays les définit, et absents sinon (non-régression).
 *
 * Le moteur est injecté avec des règles de test dérivées d'AlgeriaPayrollRules
 * (pays DZ, barèmes inchangés) surchargées uniquement sur les nouveaux
 * contrats — aucun pays réel n'est affecté.
 */

/** Règle de test : 13ème mois légal obligatoire (décembre). */
final class MandatoryThirteenthMonthRules extends AlgeriaPayrollRules
{
    public function thirteenthMonthMandatory(): bool
    {
        return true;
    }
}

/** Règle de test : taxe de minimum fiscal forfaitaire de 500 sur le brut. */
final class BracketTaxRules extends AlgeriaPayrollRules
{
    public function calculateBracketTax(float $grossSalary): float
    {
        return 500.0;
    }
}

class ThirteenthMonthTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille par défaut (test)',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 60000,
        ]);
    }

    private function makeRun(string $periodStart = '2026-12-01', string $periodEnd = '2026-12-31'): PayrollRun
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $this->company->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'country_code' => 'DZ',
            'status' => PayrollRun::STATUS_DRAFT,
        ]);

        return $run;
    }

    public function test_13th_month_injected_in_december_when_mandatory(): void
    {
        $calculator = new PayrollCalculator([new MandatoryThirteenthMonthRules]);

        $calculator->calculateRun($this->makeRun());

        /** @var PaySlip $slip */
        $slip = PaySlip::where('payroll_run_id', PayrollRun::firstOrFail()->id)->firstOrFail();

        // Brut = base 60 000 + 13ème mois 60 000 → 120 000 (traitement
        // 'fully_taxable' : soumis aux cotisations et à l'impôt).
        $this->assertSame(120000.0, $slip->gross_salary);

        $line = PaySlipLine::where('pay_slip_id', $slip->id)
            ->where('name', '13ème mois')
            ->where('type', 'earning')
            ->first();

        $this->assertNotNull($line);
        $this->assertSame(60000.0, (float) $line->amount);
    }

    public function test_13th_month_not_injected_when_not_mandatory(): void
    {
        // Règles DZ par défaut : thirteenthMonthMandatory() = false.
        $calculator = new PayrollCalculator([new AlgeriaPayrollRules]);

        $calculator->calculateRun($this->makeRun());

        /** @var PaySlip $slip */
        $slip = PaySlip::where('payroll_run_id', PayrollRun::firstOrFail()->id)->firstOrFail();

        $this->assertSame(60000.0, $slip->gross_salary);
        $this->assertNull(
            PaySlipLine::where('pay_slip_id', $slip->id)->where('name', '13ème mois')->first()
        );
    }

    public function test_13th_month_not_injected_outside_december_when_mandatory(): void
    {
        $calculator = new PayrollCalculator([new MandatoryThirteenthMonthRules]);

        // Run de juillet : pas de 13ème mois (injection réservée à décembre).
        $calculator->calculateRun($this->makeRun('2026-07-01', '2026-07-31'));

        /** @var PaySlip $slip */
        $slip = PaySlip::where('payroll_run_id', PayrollRun::firstOrFail()->id)->firstOrFail();

        $this->assertSame(60000.0, $slip->gross_salary);
        $this->assertNull(
            PaySlipLine::where('pay_slip_id', $slip->id)->where('name', '13ème mois')->first()
        );
    }

    public function test_bracket_tax_injected_when_rule_defines_it(): void
    {
        $calculator = new PayrollCalculator([new BracketTaxRules]);

        $calculator->calculateRun($this->makeRun());

        /** @var PaySlip $slip */
        $slip = PaySlip::where('payroll_run_id', PayrollRun::firstOrFail()->id)->firstOrFail();

        $line = PaySlipLine::where('pay_slip_id', $slip->id)
            ->where('name', 'Taxe de minimum fiscal')
            ->where('type', 'deduction')
            ->first();

        $this->assertNotNull($line);
        $this->assertSame(500.0, (float) $line->amount);

        // La taxe de minimum fiscal est bien incluse dans les déductions
        // totales (elle s'ajoute aux cotisations salariales + IRG).
        $this->assertGreaterThanOrEqual(500.0, $slip->total_deductions);
    }

    public function test_bracket_tax_absent_when_rule_returns_zero(): void
    {
        $calculator = new PayrollCalculator([new AlgeriaPayrollRules]);

        $calculator->calculateRun($this->makeRun());

        /** @var PaySlip $slip */
        $slip = PaySlip::where('payroll_run_id', PayrollRun::firstOrFail()->id)->firstOrFail();

        $this->assertNull(
            PaySlipLine::where('pay_slip_id', $slip->id)->where('name', 'Taxe de minimum fiscal')->first()
        );
    }
}
