<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Issue #1825 — Golden tests Côte d'Ivoire (pilot) : ITSAS + Contribution
 * Nationale + CNSS légaux (CGI 2024). Valeurs calculées à la main —
 * référence docs/payroll/CI_COMPLIANCE.md.
 */
class GoldenCiItsasTest extends TestCase
{
    private function rules(): CedeaoPayrollRules
    {
        return (new CedeaoPayrollRules)->forMemberCountry('CI');
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    public static function itsasProvider(): array
    {
        // [grossTaxable (brut − CNSS), total ITSAS + CN attendu]
        // (valeurs calculées à la main depuis les formules CI_COMPLIANCE.md)
        return [
            'brut 60 000 (1ère tranche 0 % + CN)' => [60000.0, 150.0],
            'brut 100 000 (tranche 2 % + CN)' => [100000.0, 1350.0],
            'brut 500 000 (tranche 21 % + CN)' => [500000.0, 58083.33],
            'brut 1 000 000 (tranche 21 % + CN)' => [1000000.0, 163000.0],
            'brut 3 000 000 (tranche 29 % + CN)' => [3000000.0, 655500.0],
        ];
    }

    #[DataProvider('itsasProvider')]
    public function test_golden_ci_itsas_plus_cn(float $base, float $expectedTotal): void
    {
        $total = $this->rules()->calculateIncomeTax($base);

        $this->assertSame($expectedTotal, $total);
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}>
     */
    public static function socialProvider(): array
    {
        return [
            'brut 100 000' => [100000.0, 3200.0, 12250.0],
            'brut 1 000 000' => [1000000.0, 32000.0, 122500.0],
            // AT 2,0 % NON plafonné → appliqué sur le brut complet (60 000).
            'brut 3 000 000 (plafonné retraite/famille, AT plein)' => [3000000.0, 52714.08, 228849.79],
        ];
    }

    #[DataProvider('socialProvider')]
    public function test_golden_ci_cnss(float $gross, float $expectedEmployee, float $expectedEmployer): void
    {
        $charges = $this->rules()->calculateSocialCharges($gross);

        $this->assertSame($expectedEmployee, $charges['employee']);
        $this->assertSame($expectedEmployer, $charges['employer']);
    }

    public function test_golden_ci_abatement_20_percent(): void
    {
        $this->assertSame(['rate' => 20.0, 'cap' => null], $this->rules()->professionalExpensesDeduction());
    }

    public function test_golden_ci_confidence_pilot_and_13th_month(): void
    {
        $this->assertSame('pilot', $this->rules()->confidenceLevel());
        $this->assertTrue($this->rules()->thirteenthMonthMandatory());
        $this->assertSame([['up_to_hours' => 8.0, 'multiplier' => 1.15], ['up_to_hours' => 14.0, 'multiplier' => 1.35], ['up_to_hours' => null, 'multiplier' => 1.50]], $this->rules()->overtimeRateTiers());
    }
}
