<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Mali (ML), issue #1829.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/ML_COMPLIANCE.md §N.
 */
class GoldenMlPayrollTest extends TestCase
{
    private function ml(): CedeaoPayrollRules
    {
        return new CedeaoPayrollRules('ML');
    }

    public function test_golden_ml_smig_net(): void
    {
        // Calcul manuel (ML_COMPLIANCE.md §1-§3) — SMIG 40 000 XOF :
        //   INPS salariale 3,6 % × 40 000 = 1 440
        //   Assiette ITS = 40 000 − 1 440 = 38 560 → annuel 462 720
        //     → tranche 0 % → ITS 0
        //   Net = 40 000 − 1 440 = 38 560 XOF
        $charges = $this->ml()->calculateSocialCharges(40000.0);

        $this->assertSame(1440.0, $charges['employee']);
        $this->assertSame(0.0, $this->ml()->calculateIncomeTax(38560.0));
        $this->assertSame(38560.0, round(40000.0 - $charges['employee'], 2));
    }

    public function test_golden_ml_employee_300k_its(): void
    {
        // Calcul manuel (ML_COMPLIANCE.md §1) — brut 300 000 :
        //   INPS salariale 3,6 % = 10 800 → assiette 289 200 → annuel 3 470 400
        //   Tranche 0-540k : 0 · 540k-1,32M : 780 000 × 5 % = 39 000
        //   1,32M-2,04M : 720 000 × 10 % = 72 000
        //   2,04M-3,48M : 1 430 400 × 15 % = 214 560 → total 325 560
        //     → mensuel 27 130,00
        $charges = $this->ml()->calculateSocialCharges(300000.0);
        $taxable = 300000.0 - $charges['employee'];

        $this->assertSame(10800.0, $charges['employee']);
        $this->assertSame(289200.0, $taxable);
        $this->assertSame(27130.0, $this->ml()->calculateIncomeTax($taxable));
    }

    public function test_golden_ml_inps_capped_at_3m(): void
    {
        // Calcul manuel (ML_COMPLIANCE.md §3) — brut 3 500 000 :
        //   INPS salariale 3,6 % × min(3,5M, 3M) = 108 000
        //   Patronale = 7,4 % × 3M (222 000) + 4,0 % × 3,5M (140 000)
        //     + 2,0 % × 3,5M (70 000, non plafonnés) = 432 000
        $charges = $this->ml()->calculateSocialCharges(3500000.0);

        $this->assertSame(108000.0, $charges['employee']);
        $this->assertSame(432000.0, $charges['employer']);

        // Assiette ITS = 3 500 000 − 108 000 = 3 392 000 → annuel 40 704 000
        // → ITS mensuel 933 850,00 (tranches 0/5/10/15/20/30 %)
        $this->assertSame(933850.0, $this->ml()->calculateIncomeTax(3392000.0));
    }

    public function test_golden_ml_overtime_5h(): void
    {
        // Calcul manuel (ML_COMPLIANCE.md §6) — 5 h sup au 1er palier
        // (OHADA +15 %) : taux horaire 1 730,80 (300 000 / 173,33) ;
        // 5 × 1 730,80 × 1,15 = 9 952,10 — attendu EN DUR (#1938 : la
        // mécanique horaire générique vit dans GoldenGenericEngineTest, seule
        // la valeur légale du palier reste testée ici).
        $tiers = $this->ml()->overtimeRateTiers();

        $this->assertSame(1.15, $tiers[0]);
        $this->assertSame(9952.1, round(5.0 * 1730.8 * $tiers[0], 2));
    }

    public function test_golden_ml_end_of_contract_notice_employee(): void
    {
        // Préavis légal employé/technicien 1 mois (ML_COMPLIANCE.md §7, Code du travail
        // OHADA) — valeur légale du pays. L'ancien cas verrouillait AUSSI
        // `severanceMonthsPerYear() = 1,0` — défaut GÉNÉRIQUE du moteur
        // présenté comme valeur légale (« 1 mois de base × N ans ») →
        // déplacé dans GoldenGenericEngineTest avec avertissement explicite
        // (#1938).
        $this->assertSame(30.0, $this->ml()->noticePeriodDays(5.0));
    }
}
