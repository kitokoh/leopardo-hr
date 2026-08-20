<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie DZ.
 *
 * Méthodologie (docs/focus/PLAN.md, docs/payroll/DZ_COMPLIANCE.md) :
 * chaque valeur attendue est CALCULÉE À LA MAIN (tableur/calcul manuel),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Cas couverts (référence : docs/payroll/DZ_COMPLIANCE.md §1-§2) :
 *  1. SMIG (20 000 DZD)          → IRG 0, CNAS 1 800 / 5 200
 *  2. Cadre moyen (60 000 DZD)   → IRG 8 500, CNAS 5 400 / 15 600
 *  3. Haut salaire (350 000 DZD) → IRG 101 200, CNAS 31 500 / 91 000
 */
class GoldenDzPayrollTest extends TestCase
{
    // Volontairement SANS base de données (F-13) : les règles retombent sur
    // les barèmes par défaut quand tax_slabs est vide — les tests golden de
    // règles pures ne dépendent d'aucun schéma de test.

    private function rules(): AlgeriaPayrollRules
    {
        return new AlgeriaPayrollRules();
    }

    public function test_golden_dz_irg_at_minimum_wage_20000(): void
    {
        // Calcul manuel : 20 000 DZD → tranche 0 % → impôt 0.
        $this->assertSame(0.0, $this->rules()->calculateIncomeTax(20000.0));
    }

    public function test_golden_dz_irg_at_60000(): void
    {
        // Calcul manuel (docs/payroll/DZ_COMPLIANCE.md §1) :
        //   0–20k : 0 · 20–40k : 20 000 × 23 % = 4 600 · 40–60k : 20 000 × 27 % = 5 400
        //   mensuel 10 000 → annuel 120 000 → abattement 40 % (plaf. 18 000) = 18 000
        //   IRG mensuel = (120 000 − 18 000) / 12 = 8 500
        $this->assertSame(8500.0, $this->rules()->calculateIncomeTax(60000.0));
    }

    public function test_golden_dz_irg_at_350000(): void
    {
        // Calcul manuel :
        //   0–20k : 0 · 20–40k : 4 600 · 40–80k : 10 800 · 80–160k : 24 000
        //   160–320k : 52 800 · 320–350k : 30 000 × 35 % = 10 500
        //   mensuel 102 700 → annuel 1 232 400 → abattement plafonné 18 000
        //   IRG mensuel = (1 232 400 − 18 000) / 12 = 101 200
        $this->assertSame(101200.0, $this->rules()->calculateIncomeTax(350000.0));
    }

    public function test_golden_dz_social_charges_at_60000(): void
    {
        // CNAS salariale 9 % = 5 400 · patronale 26 % = 15 600 (DZ_COMPLIANCE.md §2).
        $charges = $this->rules()->calculateSocialCharges(60000.0);

        $this->assertSame(5400.0, $charges['employee']);
        $this->assertSame(15600.0, $charges['employer']);
    }

    public function test_golden_dz_social_charges_at_minimum_wage(): void
    {
        // 20 000 × 9 % = 1 800 · 20 000 × 26 % = 5 200.
        $charges = $this->rules()->calculateSocialCharges(20000.0);

        $this->assertSame(1800.0, $charges['employee']);
        $this->assertSame(5200.0, $charges['employer']);
    }

    public function test_golden_dz_full_slip_flow_at_60000(): void
    {
        // Reproduit fidèlement PayrollCalculator::calculateSlip() :
        //   1. brut 60 000 → CNAS salariale 5 400 (9 %)
        //   2. assiette IRG = brut − CNAS salariale = 54 600 (DZ_COMPLIANCE §1bis)
        //   3. IRG(54 600) = 4 600 + 14 600×27 % = 8 542/mois → annuel 102 504
        //      → abattement 40 % plafonné 18 000 → (102 504 − 18 000)/12 = 7 042
        //   4. net = 60 000 − 5 400 − 7 042 = 47 558
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(60000.0);
        $taxable = 60000.0 - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $net = 60000.0 - $charges['employee'] - $irg;

        $this->assertSame(5400.0, $charges['employee']);
        $this->assertSame(54600.0, $taxable);
        $this->assertSame(7042.0, $irg);
        $this->assertSame(47558.0, $net);
    }

    public function test_golden_dz_irg_at_30000_minimum_abatement(): void
    {
        // #5149 — Golden test calcul à la main : 30 000 DZD (tranche 23 %)
        // Référence légale : CIDTA art. 104 (tranches IRG) + art. 104 bis
        //                    (abattement forfaitaire 40 %, plancher 12 000 DZA/an).
        //
        // Calcul manuel :
        //   Tranche 0–20 000 DZD → 0 %                   = 0
        //   Tranche 20 001–30 000 DZD : 10 000 × 23 %    = 2 300 DZD/mois
        //   Impôt mensuel avant abattement               = 2 300 DZD
        //   Impôt annuel                                 = 2 300 × 12 = 27 600 DZD
        //
        //   Abattement = max(27 600 × 40 %, plancher 12 000) plafonné 18 000
        //              = max(11 040, 12 000) = 12 000 DZD  ← plancher s'applique
        //
        //   IRG annuel net = 27 600 − 12 000 = 15 600 DZD
        //   IRG mensuel    = 15 600 / 12     = 1 300 DZD
        //
        // Cas pédagogique : seul cas où le PLANCHER d'abattement est actif
        // (40 % de 27 600 = 11 040 < plancher 12 000 DZD).
        $this->assertSame(1300.0, $this->rules()->calculateIncomeTax(30000.0));
    }

    public function test_golden_dz_irg_at_120000_maximum_abatement_cap(): void
    {
        // #5149 — Golden test calcul à la main : 120 000 DZD (tranche 30 %)
        // Référence légale : CIDTA art. 104 + art. 104 bis
        //                    (abattement forfaitaire 40 %, plafond 18 000 DZA/an).
        //
        // Calcul manuel :
        //   0–20 000 → 0 %                                = 0
        //   20 001–40 000 : 20 000 × 23 %                = 4 600 DZD/mois
        //   40 001–80 000 : 40 000 × 27 %                = 10 800 DZD/mois
        //   80 001–120 000 : 40 000 × 30 %               = 12 000 DZD/mois
        //   Impôt mensuel avant abattement               = 27 400 DZD
        //   Impôt annuel                                 = 27 400 × 12 = 328 800 DZD
        //
        //   Abattement = max(328 800 × 40 %, plancher 12 000) plafonné 18 000
        //              = max(131 520, 12 000) = 131 520 → plafonné à 18 000 DZD
        //                                            ← PLAFOND s'applique
        //
        //   IRG annuel net = 328 800 − 18 000 = 310 800 DZD
        //   IRG mensuel    = 310 800 / 12     = 25 900 DZD
        //
        // Cas pédagogique : seul cas où le PLAFOND d'abattement est actif
        // (40 % de 328 800 = 131 520 > plafond 18 000 DZD).
        $this->assertSame(25900.0, $this->rules()->calculateIncomeTax(120000.0));
    }
}
