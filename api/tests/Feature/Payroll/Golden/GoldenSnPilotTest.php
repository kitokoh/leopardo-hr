<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Issue #1827 — Golden tests Sénégal (pilot) : TRIMF, CFCE, IPRES T1/T2,
 * CSS, abattement 30 %, préavis. Valeurs calculées à la main —
 * référence docs/payroll/SN_COMPLIANCE.md.
 */
class GoldenSnPilotTest extends TestCase
{
    private function rules(): SenegalPayrollRules
    {
        return new SenegalPayrollRules;
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    public static function trimfProvider(): array
    {
        return [
            'brut 20 000' => [20000.0, 900.0],
            'brut 60 000' => [60000.0, 2700.0],
            'brut 120 000' => [120000.0, 5400.0],
            'brut 300 000' => [300000.0, 9000.0],
            'brut 500 000' => [500000.0, 18000.0],
            'brut 1 000 000' => [1000000.0, 36000.0],
        ];
    }

    #[DataProvider('trimfProvider')]
    public function test_golden_sn_trimf(float $gross, float $expected): void
    {
        $this->assertSame($expected, $this->rules()->calculateBracketTax($gross));
    }

    public function test_golden_sn_abatement_30_percent_uncapped(): void
    {
        $this->assertSame(['rate' => 30.0, 'cap' => null], $this->rules()->professionalExpensesDeduction());
    }

    public function test_golden_sn_notice_period(): void
    {
        $this->assertSame(8.0, $this->rules()->noticePeriodDays(0.5));
        $this->assertSame(30.0, $this->rules()->noticePeriodDays(2.0));
        $this->assertSame(90.0, $this->rules()->noticePeriodDays(7.0));
    }

    public function test_golden_sn_social_contributions_list(): void
    {
        $codes = array_column($this->rules()->socialContributions(), 'code');
        $this->assertContains('IPRES_SN_EMP', $codes);
        $this->assertContains('IPRES_SN_EMP_T2', $codes);
        $this->assertContains('CSS_SN_PAT_AT', $codes);
        $this->assertContains('CFCE_SN_PAT', $codes);
    }

    public function test_golden_sn_confidence_stays_pilot(): void
    {
        $this->assertSame('pilot', $this->rules()->confidenceLevel());
    }
}
