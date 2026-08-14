<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Gabon (GA), issue #1824.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/GA_COMPLIANCE.md §N.
 */
class GoldenGaPayrollTest extends TestCase
{
    private function ga(): CemacPayrollRules
    {
        return new CemacPayrollRules('GA');
    }

    public function test_golden_ga_smig_net(): void
    {
        // Calcul manuel (GA_COMPLIANCE.md §1-§3) — SMIG 150 000 XAF :
        //   CNSS salariale 2,5 % = 3 750 → assiette 146 250 → annuel 1 755 000
        //   Tranches : 0-1,5M : 0 · 1,5M-1,755M : 255 000 × 5 % = 12 750
        //     → IRPP mensuel 1 062,50
        //   Net = 150 000 − 3 750 − 1 062,50 = 145 187,50 XAF
        $charges = $this->ga()->calculateSocialCharges(150000.0);
        $taxable = 150000.0 - $charges['employee'];

        $this->assertSame(3750.0, $charges['employee']);
        $this->assertSame(1062.5, $this->ga()->calculateIncomeTax($taxable));
        $this->assertSame(145187.5, round(150000.0 - $charges['employee'] - $this->ga()->calculateIncomeTax($taxable), 2));
    }

    public function test_golden_ga_cadre_500k_irpp(): void
    {
        // Calcul manuel (GA_COMPLIANCE.md §1) — brut 500 000 :
        //   CNSS salariale 2,5 % = 12 500 → assiette 487 500 → annuel 5 850 000
        //   1,5-1,92M : 420 000 × 5 % = 21 000 · 1,92-2,7M : 780 000 × 10 % = 78 000
        //   2,7-3,6M : 900 000 × 15 % = 135 000 · 3,6-5,16M : 1 560 000 × 20 % = 312 000
        //   5,16-5,85M : 690 000 × 25 % = 172 500 → total 718 500 → mensuel 59 875
        $charges = $this->ga()->calculateSocialCharges(500000.0);
        $taxable = 500000.0 - $charges['employee'];

        $this->assertSame(12500.0, $charges['employee']);
        $this->assertSame(59875.0, $this->ga()->calculateIncomeTax($taxable));
    }

    /**
     * #1939 — verrouille l'ANNUALISATION × 12 (jamais × 10, suspicion review
     * PR #1850) et le barème officiel DGI (8 tranches annuelles) :
     *   assiette 700 000/mois → annuel 8 400 000 :
     *   1,5-1,92M : 420 000 × 5 % = 21 000 · 1,92-2,7M : 780 000 × 10 % = 78 000
     *   2,7-3,6M : 900 000 × 15 % = 135 000 · 3,6-5,16M : 1 560 000 × 20 % = 312 000
     *   5,16-7,5M : 2 340 000 × 25 % = 585 000 · 7,5-8,4M : 900 000 × 30 % = 270 000
     *   → total 1 401 000 → mensuel 116 750,00 (si × 10 : 1 167 500 → 97 291,67)
     */
    public function test_golden_ga_irpp_annualized_12_not_10(): void
    {
        $this->assertSame(116750.0, $this->ga()->calculateIncomeTax(700000.0));
    }

    public function test_golden_ga_cnss_capped_at_3m(): void
    {
        // Calcul manuel (GA_COMPLIANCE.md §3) — brut 3 500 000 :
        //   salariale 2,5 % × 3M = 75 000
        //   patronale = 5 % × 3M (150 000) + 8 % × 3M (240 000)
        //     + 3 % × 3,5M (105 000, non plafonné) = 495 000
        $charges = $this->ga()->calculateSocialCharges(3500000.0);

        $this->assertSame(75000.0, $charges['employee']);
        $this->assertSame(495000.0, $charges['employer']);

        // Assiette IRPP = 3 425 000 → annuel 41 100 000 → mensuel 1 059 666,67
        $this->assertSame(1059666.67, $this->ga()->calculateIncomeTax(3425000.0));
    }

    public function test_golden_ga_overtime_5h(): void
    {
        // Calcul manuel (GA_COMPLIANCE.md §6) — 5 h sup au 1er palier
        // (CEMAC +20 %) : taux horaire 1 730,80 (300 000 / 173,33) ;
        // 5 × 1 730,80 × 1,20 = 10 384,80 — attendu EN DUR (#1938 : la
        // mécanique horaire générique vit dans GoldenGenericEngineTest, seule
        // la valeur légale du palier reste testée ici).
        $tiers = $this->ga()->overtimeRateTiers();

        $this->assertSame(1.20, $tiers[0]);
        $this->assertSame(10384.8, round(5.0 * 1730.8 * $tiers[0], 2));
    }

    public function test_golden_ga_end_of_contract_notice_employee(): void
    {
        // Préavis légal employé/technicien 1 mois (GA_COMPLIANCE.md §7, Code du travail
        // OHADA) — valeur légale du pays. L'ancien cas verrouillait AUSSI
        // `severanceMonthsPerYear() = 1,0` — défaut GÉNÉRIQUE du moteur
        // présenté comme valeur légale (« 1 mois de base × N ans ») →
        // déplacé dans GoldenGenericEngineTest avec avertissement explicite
        // (#1938).
        $this->assertSame(30.0, $this->ga()->noticePeriodDays(5.0));
    }
}
