<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Tunisie (TN), issue #2119.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/TN_COMPLIANCE.md.
 */
class GoldenTnPayrollTest extends TestCase
{
    private function tn(): TunisiaPayrollRules
    {
        return new TunisiaPayrollRules;
    }

    public function test_golden_tn_smig_net(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1-§3) — SMIG 480 TND :
        //   CNSS salariale 9,18 % × 480 = 44,06
        //   Assiette IR = 435,94 → annuel 5 231,28
        //   Tranche 5 001–20 000 : (5 231,28 − 5 000) × 26 % = 60,13
        //     → mensuel 5,01
        //   Net = 480 − 44,06 − 5,01 = 430,93
        $charges = $this->tn()->calculateSocialCharges(480.0);
        $taxable = 480.0 - $charges['employee'];

        $this->assertSame(44.06, $charges['employee']);
        $this->assertSame(79.54, $charges['employer']);
        $this->assertSame(435.94, round($taxable, 2));
        $this->assertSame(5.01, $this->tn()->calculateIncomeTax($taxable));
        $this->assertSame(430.93, round(480.0 - $charges['employee'] - $this->tn()->calculateIncomeTax($taxable), 2));
    }

    public function test_golden_tn_cadre_1500(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1-§3) — brut 1 500 TND :
        //   CNSS 9,18 % × 1 500 = 137,70
        //   Assiette = 1 362,30 → annuel 16 347,60
        //   Tranche 5 001–20 000 : (16 347,60 − 5 000) × 26 % = 2 950,38
        //     → mensuel 245,86
        $charges = $this->tn()->calculateSocialCharges(1500.0);
        $taxable = 1500.0 - $charges['employee'];

        $this->assertSame(137.7, $charges['employee']);
        $this->assertSame(248.55, $charges['employer']);
        $this->assertSame(1362.3, round($taxable, 2));
        $this->assertSame(245.86, $this->tn()->calculateIncomeTax($taxable));
    }

    public function test_golden_tn_haut_salaire_4000(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1-§3) — brut 4 000 TND :
        //   CNSS 9,18 % × 4 000 = 367,20
        //   Assiette = 3 632,80 → annuel 43 593,60
        //   Tranches : 5 001–20 000 : 15 000 × 26 % = 3 900
        //     20 001–30 000 : 10 000 × 28 % = 2 800
        //     30 001–43 593,60 : 13 593,60 × 32 % = 4 349,95
        //     Total 11 049,95 → mensuel 920,83
        $charges = $this->tn()->calculateSocialCharges(4000.0);
        $taxable = 4000.0 - $charges['employee'];

        $this->assertSame(367.2, $charges['employee']);
        $this->assertSame(662.8, $charges['employer']);
        $this->assertSame(3632.8, round($taxable, 2));
        $this->assertSame(920.83, $this->tn()->calculateIncomeTax($taxable));
    }

    public function test_golden_tn_confidence_and_metadata(): void
    {
        $this->assertSame('pilot', $this->tn()->confidenceLevel());
        $this->assertSame('TN', $this->tn()->countryCode());
        $this->assertSame('TND', $this->tn()->currency());
        $this->assertSame(480.0, $this->tn()->minimumWage());
    }
}
