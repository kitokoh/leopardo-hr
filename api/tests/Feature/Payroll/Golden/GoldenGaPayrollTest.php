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
        //   Abattement DGI (#2118) : 1 755 000 < 4 166 666 → 20 % = 351 000
        //     → base après abattement 1 404 000 → tranche 0-1,5M @ 0 %
        //     → IRPP mensuel 0,00
        //   Net = 150 000 − 3 750 − 0 = 146 250,00 XAF
        $charges = $this->ga()->calculateSocialCharges(150000.0);
        $taxable = 150000.0 - $charges['employee'];

        $this->assertSame(3750.0, $charges['employee']);
        $this->assertSame(0.0, $this->ga()->calculateIncomeTax($taxable));
        $this->assertSame(146250.0, round(150000.0 - $charges['employee'] - $this->ga()->calculateIncomeTax($taxable), 2));
    }

    public function test_golden_ga_cadre_500k_irpp(): void
    {
        // Calcul manuel (GA_COMPLIANCE.md §1) — brut 500 000 :
        //   CNSS salariale 2,5 % = 12 500 → assiette 487 500 → annuel 5 850 000
        //   Abattement DGI (#2118) : 5 850 000 ≥ 4 166 666 → fixe 833 333
        //     → base après abattement 5 016 667
        //   1,5-1,92M : 420 000 × 5 % = 21 000 · 1,92-2,7M : 780 000 × 10 % = 78 000
        //   2,7-3,6M : 900 000 × 15 % = 135 000 · 3,6-5,016667M : 1 416 667 × 20 % = 283 333,40
        //   → total 517 333,40 → mensuel 43 111,12
        $charges = $this->ga()->calculateSocialCharges(500000.0);
        $taxable = 500000.0 - $charges['employee'];

        $this->assertSame(12500.0, $charges['employee']);
        $this->assertSame(43111.12, $this->ga()->calculateIncomeTax($taxable));
    }

    /**
     * #1939/#2118 — verrouille l'ANNUALISATION × 12 (jamais × 10, suspicion
     * review PR #1850) ET l'abattement DGI (#2118), barème officiel DGI
     * (8 tranches annuelles) :
     *   assiette 700 000/mois → annuel 8 400 000 → abattement fixe 833 333
     *   → base après abattement 7 566 667 :
     *   1,5-1,92M : 420 000 × 5 % = 21 000 · 1,92-2,7M : 780 000 × 10 % = 78 000
     *   2,7-3,6M : 900 000 × 15 % = 135 000 · 3,6-5,16M : 1 560 000 × 20 % = 312 000
     *   5,16-7,5M : 2 340 000 × 25 % = 585 000 · 7,5-7,566667M : 66 667 × 30 % = 20 000,10
     *   → total 1 151 000,10 → mensuel 95 916,68 (sans abattement : 116 750,00)
     */
    public function test_golden_ga_irpp_annualized_12_not_10(): void
    {
        $this->assertSame(95916.68, $this->ga()->calculateIncomeTax(700000.0));
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

        // Assiette IRPP = 3 425 000 → annuel 41 100 000 → abattement DGI
        // fixe 833 333 (#2118) → base 40 266 667 :
        //   0-1,5M : 0 · 1,5-1,92M : 21 000 · 1,92-2,7M : 78 000 ·
        //   2,7-3,6M : 135 000 · 3,6-5,16M : 312 000 · 5,16-7,5M : 585 000 ·
        //   7,5-11M : 3 500 000 × 30 % = 1 050 000 ·
        //   11M-40,266667M : 29 266 667 × 35 % = 10 243 333,45
        //   → total 12 424 333,45 → mensuel 1 035 361,12
        $this->assertSame(1035361.12, $this->ga()->calculateIncomeTax(3425000.0));
    }

    public function test_golden_ga_overtime_5h(): void
    {
        // Calcul manuel (GA_COMPLIANCE.md §6) — 5 h sup au 1er palier
        // (CEMAC +20 %) : taux horaire 1 730,80 (300 000 / 173,33) ;
        // 5 × 1 730,80 × 1,20 = 10 384,80 — attendu EN DUR (#1938 : la
        // mécanique horaire générique vit dans GoldenGenericEngineTest, seule
        // la valeur légale du palier reste testée ici).
        $tiers = $this->ga()->overtimeRateTiers();

        $this->assertSame(1.20, $tiers[0]['multiplier']);
        $this->assertSame(10384.8, round(5.0 * 1730.8 * $tiers[0]['multiplier'], 2));
    }

    public function test_golden_ga_end_of_contract_notice_employee(): void
    {
        // Préavis légal employé/technicien 1 mois (GA_COMPLIANCE.md §7, Code du travail
        // OHADA) — valeur légale du pays. L'ancien cas verrouillait AUSSI
        // `severanceMonthsPerYear() = 1,0` — défaut GÉNÉRIQUE du moteur
        // présenté comme valeur légale (« 1 mois de base × N ans ») →
        // déplacé dans GoldenGenericEngineTest avec avertissement explicite
        // (#1938).
        $this->assertSame(22.0, $this->ga()->noticePeriodDays(5.0));
    }
}
