<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Togo (TG), issue #2121.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/TG_COMPLIANCE.md §N.
 */
class GoldenTgPayrollTest extends TestCase
{
    private function tg(): CedeaoPayrollRules
    {
        return new CedeaoPayrollRules('TG');
    }

    public function test_golden_tg_smig_net(): void
    {
        // Calcul manuel (TG_COMPLIANCE.md §2-§4) — SMIG 52 500 XOF :
        //   CNSS salariale 4 % × 52 500 = 2 100
        //   Assiette IR = 52 500 − 2 100 = 50 400
        //   Abattement 28 % × 50 400 = 14 112 → 36 288 → annuel 435 456
        //     → tranche 0 % → IRPP 0
        //   Net = 52 500 − 2 100 = 50 400
        $charges = $this->tg()->calculateSocialCharges(52500.0);
        $taxable = 52500.0 - $charges['employee'];

        $this->assertSame(2100.0, $charges['employee']);
        $this->assertSame(0.0, $this->tg()->calculateIncomeTax($taxable));
        $this->assertSame(50400.0, round(52500.0 - $charges['employee'], 2));
    }

    public function test_golden_tg_cadre_300k_irpp(): void
    {
        // Calcul manuel (TG_COMPLIANCE.md §1-§2) — brut 300 000 :
        //   CNSS salariale 4 % = 12 000 → assiette 288 000
        //   Abattement 28 % × 288 000 = 80 640 → 207 360 → annuel 2 488 320
        //   Tranche 0-900k : 0 · 900k-3M : (2 488 320 − 900 000) × 3 %
        //     = 1 588 320 × 3 % = 47 649,60 → mensuel 3 970,80
        $charges = $this->tg()->calculateSocialCharges(300000.0);
        $taxable = 300000.0 - $charges['employee'];

        $this->assertSame(12000.0, $charges['employee']);
        $this->assertSame(288000.0, $taxable);
        $this->assertSame(3970.8, $this->tg()->calculateIncomeTax($taxable));
    }

    public function test_golden_tg_haut_salaire_800k_irpp(): void
    {
        // Calcul manuel (TG_COMPLIANCE.md §1-§2) — brut 800 000 :
        //   CNSS salariale 4 % = 32 000 → assiette 768 000
        //   Abattement 28 % × 768 000 = 215 040 → 552 960 → annuel 6 635 520
        //   Tranches : 0-900k : 0 · 900k-3M : 2 100 000 × 3 % = 63 000
        //     3M-6M : 3 000 000 × 10 % = 300 000
        //     6M-6,63552M : 635 520 × 15 % = 95 328
        //     Total 458 328 → mensuel 38 194,00
        $charges = $this->tg()->calculateSocialCharges(800000.0);
        $taxable = 800000.0 - $charges['employee'];

        $this->assertSame(32000.0, $charges['employee']);
        $this->assertSame(768000.0, $taxable);
        $this->assertSame(38194.0, $this->tg()->calculateIncomeTax($taxable));
    }

    public function test_golden_tg_abattement_capped_at_233333(): void
    {
        // Calcul manuel (TG_COMPLIANCE.md §2) — brut 1 000 000 :
        //   CNSS salariale 4 % = 40 000 → assiette 960 000
        //   Abattement 28 % × 960 000 = 268 800 > plafond 233 333,33
        //     (fraction du revenu ≤ 10 M/an, CGI art. 26) → 233 333,33
        //   Assiette après abattement = 726 666,67 → annuel 8 720 000,04
        //   Tranches : 0-900k : 0 · 900k-3M : 63 000 · 3M-6M : 300 000
        //     6M-8,72M : (8 720 000,04 − 6 000 000) × 15 % = 408 000,01
        //     Total 771 000,01 → mensuel 64 250,00
        $charges = $this->tg()->calculateSocialCharges(1000000.0);
        $taxable = 1000000.0 - $charges['employee'];

        $this->assertSame(40000.0, $charges['employee']);
        $this->assertSame(960000.0, $taxable);
        $this->assertSame(64250.0, $this->tg()->calculateIncomeTax($taxable));
    }

    public function test_golden_tg_cnss_uncapped_high_salary(): void
    {
        // Calcul manuel (TG_COMPLIANCE.md §3) — brut 2 000 000 :
        //   CNSS salariale 4 % × 2 000 000 = 80 000 (NON plafonnée)
        //   Patronale = 12,5 % (250 000) + 3 % (60 000) + 2 % (40 000)
        //     = 350 000 (NON plafonnée — assiette = totalité des revenus)
        $charges = $this->tg()->calculateSocialCharges(2000000.0);

        $this->assertSame(80000.0, $charges['employee']);
        $this->assertSame(350000.0, $charges['employer']);
    }

    public function test_golden_tg_tax_boundary_900k_annual(): void
    {
        // Calcul manuel (TG_COMPLIANCE.md §1) — assiette mensuelle après
        // abattement ~75 000 → annuel ~900 000 → dernière tranche exonérée
        // (0 %), IRPP = 0. Brut tel que brut − 4 % CNSS − 28 % abattement
        // ≈ 75 000 : 75 000 / 0,72 / 0,96 ≈ 108 506,94.
        $gross = 108506.94;
        $charges = $this->tg()->calculateSocialCharges($gross);
        $taxable = $gross - $charges['employee'];

        // Assiette après CNSS = 104 166,66 ; abattement 28 % = 29 166,66
        // → 74 999,99 → annuel 899 999,94 (< 900 000) → 0 % → IRPP 0.
        $this->assertSame(0.0, $this->tg()->calculateIncomeTax($taxable));
    }

    public function test_golden_tg_notice_period_category(): void
    {
        // Préavis légal (TG_COMPLIANCE.md §6, Code du travail art. 74) :
        // employés 30 j, cadres 90 j (niveau employé par défaut).
        $this->assertSame(30.0, $this->tg()->noticePeriodDays(5.0));
        $this->assertSame(90.0, $this->tg()->noticePeriodDays(5.0, 'cadre'));
        $this->assertSame(30.0, $this->tg()->noticePeriodDays(5.0, 'employe'));
    }

    public function test_golden_tg_confidence_pilot(): void
    {
        // TG_COMPLIANCE.md §8 — pilot tant qu'aucune validation experte.
        $this->assertSame('pilot', $this->tg()->confidenceLevel());
        $this->assertSame('TG', $this->tg()->countryCode());
        $this->assertSame(52500.0, $this->tg()->minimumWage());
        $this->assertNull($this->tg()->verificationDate());
    }
}
