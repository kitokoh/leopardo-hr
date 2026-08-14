<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Burkina Faso (BF), issue
 * #1829. Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans
 * un commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/BF_COMPLIANCE.md §N.
 */
class GoldenBfPayrollTest extends TestCase
{
    private function bf(): CedeaoPayrollRules
    {
        return new CedeaoPayrollRules('BF');
    }

    public function test_golden_bf_smig_net(): void
    {
        // Calcul manuel (BF_COMPLIANCE.md §1-§3) — SMIG 34 664 XOF :
        //   CNSS salariale 5,5 % × 34 664 = 1 906,52
        //   Assiette IUTS = 34 664 − 1 906,52 = 32 757,48 → annuel 393 089,76
        //     → tranche 0 % → IUTS 0
        //   Net = 34 664 − 1 906,52 = 32 757,48 XOF
        $charges = $this->bf()->calculateSocialCharges(34664.0);
        $taxable = 34664.0 - $charges['employee'];

        $this->assertSame(1906.52, $charges['employee']);
        $this->assertSame(0.0, $this->bf()->calculateIncomeTax($taxable));
        $this->assertSame(32757.48, round(34664.0 - $charges['employee'], 2));
    }

    public function test_golden_bf_cadre_300k_iuts(): void
    {
        // Calcul manuel (BF_COMPLIANCE.md §1) — brut 300 000 :
        //   CNSS salariale 5,5 % = 16 500 → assiette 283 500 → annuel 3 402 000
        //   Tranche 0-600k : 0 · 600k-1,5M : 900 000 × 12,1 % = 108 900
        //   1,5M-3M : 1 500 000 × 13,9 % = 208 500
        //   3M-3,402M : 402 000 × 18,7 % = 75 174 → total 392 574 → mensuel 32 714,50
        $charges = $this->bf()->calculateSocialCharges(300000.0);
        $taxable = 300000.0 - $charges['employee'];

        $this->assertSame(16500.0, $charges['employee']);
        $this->assertSame(283500.0, $taxable);
        $this->assertSame(32714.5, $this->bf()->calculateIncomeTax($taxable));
    }

    public function test_golden_bf_cnss_capped_at_900k(): void
    {
        // Calcul manuel (BF_COMPLIANCE.md §3) — brut 1 200 000 :
        //   CNSS salariale 5,5 % × min(1,2M, 900k) = 49 500
        //   Patronale = 6,5 % × 900k (58 500) + 7,0 % × 900k (63 000)
        //     + 3,5 % × 1,2M (42 000, non plafonné) = 163 500
        $charges = $this->bf()->calculateSocialCharges(1200000.0);

        $this->assertSame(49500.0, $charges['employee']);
        $this->assertSame(163500.0, $charges['employer']);

        // Assiette IUTS = 1 200 000 − 49 500 = 1 150 500 → annuel 13 806 000
        // → IUTS mensuel 232 843,00 (tranches 0/12,1/13,9/18,7/23,6 %)
        $this->assertSame(232843.0, $this->bf()->calculateIncomeTax(1150500.0));
    }

    public static function prorataProvider(): array
    {
        return [
            'entrée le 15 (12/22)' => [300000.0, 22.0, 12.0, 163636.36],
            'sortie le 10 (7/22)'  => [300000.0, 22.0, 7.0, 95454.55],
        ];
    }

    #[DataProvider('prorataProvider')]
    public function test_golden_bf_prorated_base(float $base, float $working, float $actual, float $expected): void
    {
        // Calcul manuel (méthode F-05) : base × (jours travaillés / jours ouvrés).
        $this->assertSame($expected, (new PayrollCalculator())->computeProratedBase($base, $working, $actual));
    }

    public function test_golden_bf_overtime_5h(): void
    {
        // Calcul manuel (BF_COMPLIANCE.md §6) — 5 h sup (+15 %) :
        //   taux horaire = round(300 000 / 173,33, 2) = 1 730,80
        //   5 × 1 730,80 × 1,15 = 9 952,10
        $hourly = round(300000.0 / PayrollCalculator::MONTHLY_HOURS, 2);
        $expected = round(5.0 * $hourly * 1.15, 2);

        $this->assertSame(1730.8, $hourly);
        $this->assertSame(9952.1, $expected);
        $this->assertSame(40.0, $this->bf()->overtimeThresholdWeeklyHours());
    }

    public function test_golden_bf_end_of_contract_5_years(): void
    {
        // Calcul manuel (BF_COMPLIANCE.md §7) — fin de contrat à 5 ans :
        //   préavis employé 1 mois = 300 000 ; indemnité de licenciement
        //   = 1 mois de base × 5 ans = 1 500 000 XOF.
        $this->assertSame(30.0, $this->bf()->noticePeriodDays(5.0));
        $this->assertSame(300000.0, round($this->bf()->noticePeriodDays(5.0) / 30.0 * 300000.0, 2));
        $this->assertSame(1500000.0, round($this->bf()->severanceMonthsPerYear(5.0) * 5.0 * 300000.0, 2));
    }
}
