<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Congo Brazzaville (CG),
 * issue #1824. Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN
 * dans un commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/CG_COMPLIANCE.md §N.
 */
class GoldenCgPayrollTest extends TestCase
{
    private function cg(): CemacPayrollRules
    {
        return new CemacPayrollRules('CG');
    }

    public function test_golden_cg_smig_net(): void
    {
        // Calcul manuel (CG_COMPLIANCE.md §1-§3) — SMIG 90 000 XAF :
        //   CNSS salariale 4,0 % = 3 600 → assiette 86 400 → annuel 1 036 800
        //   Tranches : 0-464k : 0 · 464k-1M : 536 000 × 1 % = 5 360
        //     1M-1,0368M : 36 800 × 10 % = 3 680 → total 9 040 → mensuel 753,33
        //   Net = 90 000 − 3 600 − 753,33 = 85 646,67 XAF
        $charges = $this->cg()->calculateSocialCharges(90000.0);
        $taxable = 90000.0 - $charges['employee'];

        $this->assertSame(3600.0, $charges['employee']);
        $this->assertSame(753.33, $this->cg()->calculateIncomeTax($taxable));
        $this->assertSame(85646.67, round(90000.0 - $charges['employee'] - $this->cg()->calculateIncomeTax($taxable), 2));
    }

    public function test_golden_cg_employee_300k_irpp(): void
    {
        // Calcul manuel (CG_COMPLIANCE.md §1) — brut 300 000 :
        //   CNSS salariale 4,0 % = 12 000 → assiette 288 000 → annuel 3 456 000
        //   464k-1M : 536 000 × 1 % = 5 360 · 1M-3M : 2 000 000 × 10 % = 200 000
        //   3M-3,456M : 456 000 × 25 % = 114 000 → total 319 360 → mensuel 26 613,33
        $charges = $this->cg()->calculateSocialCharges(300000.0);
        $taxable = 300000.0 - $charges['employee'];

        $this->assertSame(12000.0, $charges['employee']);
        $this->assertSame(26613.33, $this->cg()->calculateIncomeTax($taxable));
    }

    public function test_golden_cg_cnss_capped_at_2_5m(): void
    {
        // Calcul manuel (CG_COMPLIANCE.md §3) — brut 3 000 000 :
        //   salariale 4,0 % × 2,5M = 100 000
        //   patronale = 8 % × 2,5M (200 000) + 10 % × 2,5M (250 000)
        //     + 3 % × 3M (90 000, non plafonné) = 540 000
        $charges = $this->cg()->calculateSocialCharges(3000000.0);

        $this->assertSame(100000.0, $charges['employee']);
        $this->assertSame(540000.0, $charges['employer']);

        // Assiette IRPP = 2 900 000 → annuel 34 800 000 → mensuel 1 105 446,67
        $this->assertSame(1105446.67, $this->cg()->calculateIncomeTax(2900000.0));
    }

    public function test_golden_cg_overtime_5h(): void
    {
        // Calcul manuel (CG_COMPLIANCE.md §6) — 5 h sup au 1er palier
        // (CEMAC +20 %) : taux horaire 1 730,80 (300 000 / 173,33) ;
        // 5 × 1 730,80 × 1,20 = 10 384,80 — attendu EN DUR (#1938 : la
        // mécanique horaire générique vit dans GoldenGenericEngineTest, seule
        // la valeur légale du palier reste testée ici).
        $tiers = $this->cg()->overtimeRateTiers();

        $this->assertSame(1.20, $tiers[0]['multiplier']);
        $this->assertSame(10384.8, round(5.0 * 1730.8 * $tiers[0]['multiplier'], 2));
    }

    public function test_golden_cg_end_of_contract_notice_employee(): void
    {
        // Préavis légal employé/technicien 1 mois (CG_COMPLIANCE.md §7, Code du travail
        // OHADA) — valeur légale du pays. L'ancien cas verrouillait AUSSI
        // `severanceMonthsPerYear() = 1,0` — défaut GÉNÉRIQUE du moteur
        // présenté comme valeur légale (« 1 mois de base × N ans ») →
        // déplacé dans GoldenGenericEngineTest avec avertissement explicite
        // (#1938).
        $this->assertSame(22.0, $this->cg()->noticePeriodDays(5.0));
    }
}
