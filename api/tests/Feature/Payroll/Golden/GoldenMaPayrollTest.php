<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use Tests\TestCase;

/**
 * Golden tests paie Maroc (MA), issue #2260.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/MA_COMPLIANCE.md §1-§3.
 */
class GoldenMaPayrollTest extends TestCase
{
    private function ma(): MoroccoPayrollRules
    {
        return new MoroccoPayrollRules;
    }

    public function test_golden_ma_smig_3111_ir_zero_after_abatement(): void
    {
        // Calcul manuel (MA_COMPLIANCE.md §1-§3) — SMIG 3 111 MAD/mois :
        //   CNSS salariale 4,48 % × 3 111 (plafond 6 000) = 139,37
        //   AMO salariale 2,26 % × 3 111 = 70,31
        //   → assiette imposable = 3 111 − 209,68 = 2 901,32 → annuel 34 815,84
        //   Abattement art. 58 : 35 % × 3 111 × 12 = 13 066,20 (entre 2 500 et 30 000)
        //     → base après abattement 21 749,64 → tranche 0-30 000 @ 0 %
        //     → IR mensuel 0,00 (SANS abattement : 481,58/an → 40,13/mois)
        $charges = $this->ma()->calculateSocialCharges(3111.0);
        $taxable = 3111.0 - $charges['employee'];

        $this->assertSame(209.68, round($charges['employee'], 2));
        $this->assertSame(0.0, $this->ma()->calculateIncomeTax($taxable, 12, 3111.0));
    }

    public function test_golden_ma_abatement_dedicated_method(): void
    {
        // Plancher 2 500 : 35 % d'un petit revenu annuel reste < 2 500 → 2 500.
        $this->assertSame(2500.0, $this->ma()->moroccoProfessionalExpensesAbatement(1000.0));
        // 35 % de 42 000 = 14 700 → dans la fourchette.
        $this->assertSame(14700.0, $this->ma()->moroccoProfessionalExpensesAbatement(42000.0));
        // Plafond 30 000 : 35 % de 120 000 = 42 000 → 30 000.
        $this->assertSame(30000.0, $this->ma()->moroccoProfessionalExpensesAbatement(120000.0));
    }

    public function test_golden_ma_cadre_10000_ir(): void
    {
        // Calcul manuel — brut 10 000 MAD/mois :
        //   CNSS salariale 4,48 % × 6 000 (plafonné) = 268,80
        //   AMO salariale 2,26 % × 10 000 = 226,00 → total 494,80
        //   → assiette 9 505,20 → annuel 114 062,40
        //   Abattement : 35 % × 120 000 = 42 000 → plafonné 30 000
        //     → base 84 062,40 → tranche 80 001-180 000 @ 34 % − 17 200
        //     = 28 581,22 − 17 200 = 11 381,22/an → 948,43/mois
        $charges = $this->ma()->calculateSocialCharges(10000.0);
        $taxable = 10000.0 - $charges['employee'];

        $this->assertSame(494.8, round($charges['employee'], 2));
        $this->assertSame(948.43, $this->ma()->calculateIncomeTax($taxable, 12, 10000.0));
    }

    public function test_golden_ma_high_salary_50000_ir(): void
    {
        // Calcul manuel — brut 50 000 MAD/mois :
        //   CNSS salariale 4,48 % × 6 000 = 268,80 ; AMO 2,26 % × 50 000 = 1 130,00
        //   → assiette 48 601,20 → annuel 583 214,40
        //   Abattement : 35 % × 600 000 = 210 000 → plafonné 30 000
        //     → base 553 214,40 → tranche 180 001+ @ 38 % − 24 400
        //     = 210 221,47 − 24 400 = 185 821,47/an → 15 485,12/mois
        $charges = $this->ma()->calculateSocialCharges(50000.0);
        $taxable = 50000.0 - $charges['employee'];

        $this->assertSame(1398.8, round($charges['employee'], 2));
        $this->assertSame(15485.12, $this->ma()->calculateIncomeTax($taxable, 12, 50000.0));
    }
}
