<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Maroc (MA), issue #2119.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/MA_COMPLIANCE.md.
 */
class GoldenMaPayrollTest extends TestCase
{
    private function ma(): MoroccoPayrollRules
    {
        return new MoroccoPayrollRules;
    }

    public function test_golden_ma_smig_net(): void
    {
        // Calcul manuel (MA_COMPLIANCE.md §1-§3) — SMIG 3 111 MAD :
        //   CNSS salariale 4,48 % × 3 111 = 139,37 (plafond 6 000 non atteint)
        //   AMO salariale 2,26 % × 3 111 = 70,31
        //   Total salarial = 209,68
        //   Assiette IR = 3 111 − 209,68 = 2 901,32 → annuel 34 815,84
        //   Tranche 30 001–50 000 : 34 815,84 × 10 % − 3 000 = 481,58
        //     → mensuel 40,13
        //   Net = 3 111 − 209,68 − 40,13 = 2 861,19
        $charges = $this->ma()->calculateSocialCharges(3111.0);
        $taxable = 3111.0 - $charges['employee'];

        $this->assertSame(209.68, $charges['employee']);
        $this->assertSame(407.23, $charges['employer']);
        $this->assertSame(2901.32, round($taxable, 2));
        $this->assertSame(40.13, $this->ma()->calculateIncomeTax($taxable));
        $this->assertSame(2861.19, round(3111.0 - $charges['employee'] - $this->ma()->calculateIncomeTax($taxable), 2));
    }

    public function test_golden_ma_cadre_8000(): void
    {
        // Calcul manuel (MA_COMPLIANCE.md §1-§3) — brut 8 000 MAD :
        //   CNSS 4,48 % × min(8 000, 6 000) = 268,80 (plafonné)
        //   AMO 2,26 % × 8 000 = 180,80 → total salarial 449,60
        //   Assiette = 7 550,40 → annuel 90 604,80
        //   Tranche 80 001–180 000 : 90 604,80 × 34 % − 17 200 = 13 605,63
        //     → mensuel 1 133,80
        $charges = $this->ma()->calculateSocialCharges(8000.0);
        $taxable = 8000.0 - $charges['employee'];

        $this->assertSame(449.6, $charges['employee']);
        $this->assertSame(867.6, $charges['employer']);
        $this->assertSame(7550.4, round($taxable, 2));
        $this->assertSame(1133.8, $this->ma()->calculateIncomeTax($taxable));
    }

    public function test_golden_ma_haut_salaire_20000(): void
    {
        // Calcul manuel (MA_COMPLIANCE.md §1-§3) — brut 20 000 MAD :
        //   CNSS 4,48 % × 6 000 = 268,80 (plafonné)
        //   AMO 2,26 % × 20 000 = 452,00 → total salarial 720,80
        //   Assiette = 19 279,20 → annuel 231 350,40
        //   Tranche > 180 000 : 231 350,40 × 38 % − 24 400 = 63 513,15
        //     → mensuel 5 292,76
        $charges = $this->ma()->calculateSocialCharges(20000.0);
        $taxable = 20000.0 - $charges['employee'];

        $this->assertSame(720.8, $charges['employee']);
        $this->assertSame(1360.8, $charges['employer']);
        $this->assertSame(19279.2, round($taxable, 2));
        $this->assertSame(5292.76, $this->ma()->calculateIncomeTax($taxable));
    }

    public function test_golden_ma_confidence_and_metadata(): void
    {
        $this->assertSame('pilot', $this->ma()->confidenceLevel());
        $this->assertSame('MA', $this->ma()->countryCode());
        $this->assertSame('MAD', $this->ma()->currency());
        $this->assertSame(3111.0, $this->ma()->minimumWage());
    }
}
