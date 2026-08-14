<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
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
        // → IUTS mensuel 258 212,50 (tranches 0/12,1/13,9/18,7/23,6/27,5 % —
        // tranche 27,5 % > 6 M ajoutée par #1915 ; l'ancien barème fusionné
        // donnait 232 843,00 → sous-imposition au-delà de ~500 k/mois).
        //   Recalcul manuel :
        //   0–600k : 0 · 600k–1,5M : 900 000 × 12,1 % = 108 900
        //   1,5M–3M : 1 500 000 × 13,9 % = 208 500
        //   3M–4,5M : 1 500 000 × 18,7 % = 280 500
        //   4,5M–6M : 1 500 000 × 23,6 % = 354 000
        //   > 6M : (13 806 000 − 6 000 000) × 27,5 % = 2 146 650
        //   Total 3 098 550 / 12 = 258 212,50
        $this->assertSame(258212.5, $this->bf()->calculateIncomeTax(1150500.0));
    }

    public function test_golden_bf_iuts_boundary_6m_annual(): void
    {
        // Cas frontal #1915 — assiette mensuelle 500 000 → annuel 6 000 000,
        // exactement à la limite 23,6 % / 27,5 % :
        //   0–600k : 0 · 600k–1,5M : 108 900 · 1,5M–3M : 208 500
        //   3M–4,5M : 280 500 · 4,5M–6M : 354 000 · > 6M : 0
        //   Total 951 900 / 12 = 79 325,00
        $this->assertSame(79325.0, $this->bf()->calculateIncomeTax(500000.0));
    }

    public function test_golden_bf_iuts_above_6m_annual_applies_27_5(): void
    {
        // Cas frontal #1915 — assiette mensuelle 525 000 → annuel 6 300 000,
        // au-dessus de la limite : 951 900 + 300 000 × 27,5 % = 1 034 400
        // → mensuel 86 200,00. Sans la tranche 27,5 %, le taux marginal
        // resterait bloqué à 23,6 % (sous-imposition).
        $this->assertSame(86200.0, $this->bf()->calculateIncomeTax(525000.0));
    }

    public function test_golden_bf_overtime_threshold_40h_legal(): void
    {
        // #1938 : seule la valeur LÉGALE pays reste dans le golden (BF_COMPLIANCE
        // §6 — seuil 40 h/semaine, majorations 1,15/1,35) ; la mécanique du taux
        // horaire (base / 173,33 h) est centralisée dans GoldenEngineGenericTest.
        $this->assertSame(40.0, $this->bf()->overtimeThresholdWeeklyHours());
    }

    public function test_golden_bf_notice_period_30_days_legal(): void
    {
        // #1938 : valeur légale sourcée (BF_COMPLIANCE.md §7 — préavis employé
        // 30 j) ; le défaut générique severanceMonthsPerYear() = 1,0 n'est PAS
        // verrouillé ici comme valeur légale (à confirmer expert — cf. #1938).
        $this->assertSame(30.0, $this->bf()->noticePeriodDays(5.0));
    }
}
