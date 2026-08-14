<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie France (FR), issue #2119.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/FR_COMPLIANCE.md.
 *
 * NB : barème indicatif (tranches IR annuelles 2024 simplifiées) et taux
 * sociaux pilotes — pas un substitut à un logiciel DSN certifié
 * (complianceWarning() FR).
 */
class GoldenFrPayrollTest extends TestCase
{
    private function fr(): FrancePayrollRules
    {
        return new FrancePayrollRules;
    }

    public function test_golden_fr_smic_net(): void
    {
        // Calcul manuel (FR_COMPLIANCE.md §1-§3) — SMIC 1 766 EUR :
        //   SS salariale 7,5 % × 1 766 = 132,45
        //   CSG 9,2 % × (1 766 × 0,9825 = 1 735,095) = 159,63
        //   CRDS 0,5 % × 1 735,095 = 8,68
        //   Total salarial (arrondi sur la somme, comme le code) = 300,75
        //   Assiette IR = 1 465,25 → annuel 17 583,00
        //   Tranche 11 295–28 797 : (17 583,00 − 11 294) × 11 % = 691,79
        //     → mensuel 57,65
        $charges = $this->fr()->calculateSocialCharges(1766.0);
        $taxable = 1766.0 - $charges['employee'];

        $this->assertSame(300.75, $charges['employee']);
        $this->assertSame(529.8, $charges['employer']);
        $this->assertSame(1465.25, round($taxable, 2));
        $this->assertSame(57.65, $this->fr()->calculateIncomeTax($taxable));
    }

    public function test_golden_fr_cadre_3000(): void
    {
        // Calcul manuel (FR_COMPLIANCE.md §1-§3) — brut 3 000 EUR :
        //   SS 7,5 % × 3 000 = 225,00
        //   CSG 9,2 % × (3 000 × 0,9825 = 2 947,50) = 271,17
        //   CRDS 0,5 % × 2 947,50 = 14,74 → total salarial 510,91
        //   Assiette = 2 489,09 → annuel 29 869,08
        //   Tranches : 11 295–28 797 : 17 503 × 11 % = 1 925,33
        //     28 798–29 869,08 : 1 072,08 × 30 % = 321,62
        //     Total 2 246,95 → mensuel 187,25
        $charges = $this->fr()->calculateSocialCharges(3000.0);
        $taxable = 3000.0 - $charges['employee'];

        $this->assertSame(510.91, $charges['employee']);
        $this->assertSame(900.0, $charges['employer']);
        $this->assertSame(2489.09, round($taxable, 2));
        $this->assertSame(187.25, $this->fr()->calculateIncomeTax($taxable));
    }

    public function test_golden_fr_haut_salaire_8000(): void
    {
        // Calcul manuel (FR_COMPLIANCE.md §1-§3) — brut 8 000 EUR :
        //   SS 7,5 % × 8 000 = 600,00
        //   CSG 9,2 % × 7 860 = 723,12 · CRDS 0,5 % × 7 860 = 39,30
        //   Total salarial = 1 362,42
        //   Assiette = 6 637,58 → annuel 79 650,96
        //   Tranches : 11 295–28 797 : 17 503 × 11 % = 1 925,33
        //     28 798–79 650,96 : 50 852,96 × 30 % = 15 256,19
        //     Total 17 181,52 → mensuel 1 431,79
        $charges = $this->fr()->calculateSocialCharges(8000.0);
        $taxable = 8000.0 - $charges['employee'];

        $this->assertSame(1362.42, $charges['employee']);
        $this->assertSame(2400.0, $charges['employer']);
        $this->assertSame(6637.58, round($taxable, 2));
        $this->assertSame(1431.79, $this->fr()->calculateIncomeTax($taxable));
    }

    public function test_golden_fr_confidence_and_metadata(): void
    {
        $this->assertSame('pilot', $this->fr()->confidenceLevel());
        $this->assertSame('FR', $this->fr()->countryCode());
        $this->assertSame('EUR', $this->fr()->currency());
        $this->assertSame(1766.0, $this->fr()->minimumWage());
        $this->assertStringContainsString('not', strtolower($this->fr()->complianceWarning()));
    }
}
