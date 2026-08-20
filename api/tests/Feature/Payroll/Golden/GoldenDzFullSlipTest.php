<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * Issue #5149 — golden tests « bulletin complet » (flux brut → net) DZ.
 *
 * Même méthodologie que GoldenDzPayrollTest (F-03) : chaque valeur attendue
 * est CALCULÉE À LA MAIN (docs/payroll/DZ_COMPLIANCE.md §1-§2), jamais
 * reprise du code — une divergence = régression de conformité.
 *
 * La mécanique reproduit fidèlement PayrollCalculator::calculateSlip() :
 *   1. brut → CNAS salariale 9 % (rondie) ;
 *   2. assiette IRG = brut − CNAS salariale (NON arrondie — politique
 *      d'arrondi docs/payroll/CALCULATION_CONTRACT.md) ;
 *   3. IRG(assiette) : barème progressif mensuel (CIDTA art. 104) × 12,
 *      abattement 40 % plancher 12 000 / plafond 18 000 DZD/an
 *      (CIDTA art. 104 bis), ÷ 12, arrondi 2 décimales ;
 *   4. net = brut − CNAS salariale − IRG (plancher 0, arrondi 2 décimales) ;
 *   5. coût employeur = brut + CNAS patronale 26 %.
 *
 * Cas couverts (trous de la matrice #5149 vs les cas F-03 existants) :
 *  - SMIG 20 000 (net au salaire minimum) ;
 *  - haut salaire 350 000 et tranche maximale 35 % (500 000) ;
 *  - période partielle (entrée 15/07 → 12,06/22 j) ;
 *  - absence déduite (21/22 j) ;
 *  - heures supplémentaires intégrées au brut (10 h @25 % + 5 h @50 %) ;
 *  - arrondi aux centimes (brut 33 333,33 DZD).
 *
 * Volontairement SANS base de données (F-13) : AlgeriaPayrollRules retombe
 * sur les barèmes par défaut quand tax_slabs est vide.
 */
class GoldenDzFullSlipTest extends TestCase
{
    private function rules(): AlgeriaPayrollRules
    {
        return new AlgeriaPayrollRules;
    }

    public function test_golden_dz_full_slip_at_minimum_wage_20000(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §1-§3, CIDTA art. 104) :
        //   brut 20 000 DZD (SMIG, minimumWage()) → CNAS salariale 9 % = 1 800
        //   assiette IRG = 20 000 − 1 800 = 18 200 → tranche 0 % → IRG = 0
        //   net = 20 000 − 1 800 − 0 = 18 200
        //   CNAS patronale 26 % = 5 200 → coût employeur = 20 000 + 5 200 = 25 200
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(20000.0);
        $taxable = 20000.0 - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $net = round(20000.0 - ($charges['employee'] + $irg), 2);
        $cost = round(20000.0 + $charges['employer'], 2);

        $this->assertSame(1800.0, $charges['employee']);
        $this->assertSame(18200.0, $taxable);
        $this->assertSame(0.0, $irg);
        $this->assertSame(18200.0, $net);
        $this->assertSame(5200.0, $charges['employer']);
        $this->assertSame(25200.0, $cost);
    }

    public function test_golden_dz_full_slip_at_high_salary_350000(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §1-§2) :
        //   CNAS salariale = 350 000 × 9 % = 31 500 → assiette 318 500
        //   IRG(318 500) : 0 + 4 600 (20k×23 %) + 10 800 (40k×27 %)
        //     + 24 000 (80k×30 %) + 158 500×33 % = 52 305
        //     → mensuel 91 705 → annuel 1 100 460
        //     → abattement 40 % plafonné 18 000 (CIDTA art. 104 bis)
        //     → IRG mensuel = (1 100 460 − 18 000) / 12 = 90 205
        //   net = 350 000 − 31 500 − 90 205 = 228 295
        //   patronale = 91 000 → coût employeur = 441 000
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(350000.0);
        $taxable = 350000.0 - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $net = round(350000.0 - ($charges['employee'] + $irg), 2);
        $cost = round(350000.0 + $charges['employer'], 2);

        $this->assertSame(31500.0, $charges['employee']);
        $this->assertSame(318500.0, $taxable);
        $this->assertSame(90205.0, $irg);
        $this->assertSame(228295.0, $net);
        $this->assertSame(91000.0, $charges['employer']);
        $this->assertSame(441000.0, $cost);
    }

    public function test_golden_dz_full_slip_at_top_tax_bracket_500000(): void
    {
        // Calcul manuel (CIDTA art. 104 — tranche maximale 35 %) :
        //   CNAS salariale = 500 000 × 9 % = 45 000 → assiette 455 000
        //   IRG(455 000) : 4 600 + 10 800 + 24 000 + 52 800 (160k×33 %)
        //     + 135 000×35 % = 47 250 → mensuel 139 450 → annuel 1 673 400
        //     → abattement plafonné 18 000 → IRG mensuel = 137 950
        //   net = 500 000 − 45 000 − 137 950 = 317 050
        //   patronale = 130 000 → coût employeur = 630 000
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(500000.0);
        $taxable = 500000.0 - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $net = round(500000.0 - ($charges['employee'] + $irg), 2);
        $cost = round(500000.0 + $charges['employer'], 2);

        $this->assertSame(45000.0, $charges['employee']);
        $this->assertSame(455000.0, $taxable);
        $this->assertSame(137950.0, $irg);
        $this->assertSame(317050.0, $net);
        $this->assertSame(130000.0, $charges['employer']);
        $this->assertSame(630000.0, $cost);
    }

    public function test_golden_dz_full_slip_with_absence_21_of_22_days(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §5 — absence déduite) :
        //   base proratisée = 60 000 × 21/22 = 57 272,73 (retenue 2 727,27)
        //   CNAS salariale = 57 272,73 × 9 % = 5 154,55
        //   assiette IRG = 57 272,73 − 5 154,55 = 52 118,18
        //   IRG(52 118,18) : 4 600 + 12 118,18×27 % = 7 871,91
        //     → annuel 94 462,90 → abattement plafonné 18 000
        //     → IRG mensuel = (94 462,90 − 18 000) / 12 = 6 371,91
        //   net = 57 272,73 − 5 154,55 − 6 371,91 = 45 746,27
        //   patronale = 14 890,91 → coût employeur = 72 163,64
        $base = (new PayrollCalculator)->computeProratedBase(60000.0, 22.0, 21.0);
        $this->assertSame(57272.73, $base);

        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges($base);
        $taxable = $base - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $net = round($base - ($charges['employee'] + $irg), 2);
        $cost = round($base + $charges['employer'], 2);

        $this->assertSame(5154.55, $charges['employee']);
        $this->assertSame(52118.18, $taxable);
        $this->assertSame(6371.91, $irg);
        $this->assertSame(45746.27, $net);
        $this->assertSame(14890.91, $charges['employer']);
        $this->assertSame(72163.64, $cost);
    }

    public function test_golden_dz_full_slip_partial_month_entry_mid_july(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §5 — entrée 15/07) :
        //   jours travaillés = 22 × 17/31 = 12,06 j → base 60 000 × 12,06/22 = 32 890,91
        //   CNAS salariale = 32 890,91 × 9 % = 2 960,18
        //   assiette IRG = 32 890,91 − 2 960,18 = 29 930,73
        //   IRG(29 930,73) : 9 930,73×23 % = 2 284,07 → annuel 27 408,81
        //     → abattement = max(27 408,81×40 % ; plancher 12 000) = 12 000 ← plancher actif
        //     → IRG mensuel = (27 408,81 − 12 000) / 12 = 1 284,07
        //   net = 32 890,91 − 2 960,18 − 1 284,07 = 28 646,66
        //   patronale = 8 551,64 → coût employeur = 41 442,55
        $base = (new PayrollCalculator)->computeProratedBase(60000.0, 22.0, 12.06);
        $this->assertSame(32890.91, $base);

        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges($base);
        $taxable = $base - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $net = round($base - ($charges['employee'] + $irg), 2);
        $cost = round($base + $charges['employer'], 2);

        $this->assertSame(2960.18, $charges['employee']);
        // L'assiette n'est PAS arrondie par le moteur (brut − CNAS) :
        // 32 890,91 − 2 960,18 = 29 930,730000000003 en binaire flottant —
        // l'impôt est calculé sur cette valeur exacte, puis arrondi en sortie.
        $this->assertEqualsWithDelta(29930.73, $taxable, 0.0001);
        $this->assertSame(1284.07, $irg);
        $this->assertSame(28646.66, $net);
        $this->assertSame(8551.64, $charges['employer']);
        $this->assertSame(41442.55, $cost);
    }

    public function test_golden_dz_full_slip_with_overtime_15_hours(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §5 — heures sup intégrées) :
        //   HS = 10 h × 346,160503 × 1,25 + 5 h × 346,160503 × 1,50 = 6 923,21
        //   (taux horaire = 60 000 / 173,33, précision complète #2685)
        //   brut = 60 000 + 6 923,21 = 66 923,21
        //   CNAS salariale = 66 923,21 × 9 % = 6 023,09
        //   assiette IRG = 66 923,21 − 6 023,09 = 60 900,12
        //   IRG(60 900,12) : 4 600 + 20 900,12×27 % = 10 243,03
        //     → annuel 122 916,39 → abattement plafonné 18 000 → IRG mensuel 8 743,03
        //   net = 66 923,21 − 6 023,09 − 8 743,03 = 52 157,09
        //   patronale = 17 400,03 → coût employeur = 84 323,24
        $overtime = (new PayrollCalculator)->computeOvertimePay(60000.0, 15.0);
        $this->assertSame(6923.21, $overtime);

        $gross = 60000.0 + $overtime;
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges($gross);
        $taxable = $gross - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $net = round($gross - ($charges['employee'] + $irg), 2);
        $cost = round($gross + $charges['employer'], 2);

        $this->assertSame(6023.09, $charges['employee']);
        // Idem : assiette NON arrondie — 66 923,21 − 6 023,09 = 60 900,12000000001
        // en binaire flottant ; l'IRG est calculé sur la valeur exacte.
        $this->assertEqualsWithDelta(60900.12, $taxable, 0.0001);
        $this->assertSame(8743.03, $irg);
        $this->assertSame(52157.09, $net);
        $this->assertSame(17400.03, $charges['employer']);
        $this->assertSame(84323.24, $cost);
    }

    public function test_golden_dz_full_slip_rounding_centimes_33333(): void
    {
        // Calcul manuel (cas limite arrondi aux centimes, CIDTA art. 104) :
        //   brut 33 333,33 DZD → CNAS salariale = 33 333,33 × 9 % = 2 999,9997 → 3 000,00
        //   assiette IRG = 33 333,33 − 3 000,00 = 30 333,33
        //   IRG(30 333,33) : 10 333,33×23 % = 2 376,67 → annuel 28 520,00
        //     → abattement = max(28 520×40 % ; plancher 12 000) = 12 000 ← plancher actif
        //     → IRG mensuel = (28 520,00 − 12 000) / 12 = 1 376,67
        //   net = 33 333,33 − 3 000,00 − 1 376,67 = 28 956,66
        //   CNAS patronale = 33 333,33 × 26 % = 8 666,6658 → 8 666,67
        //   coût employeur = 33 333,33 + 8 666,67 = 42 000,00
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(33333.33);
        $taxable = 33333.33 - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $net = round(33333.33 - ($charges['employee'] + $irg), 2);
        $cost = round(33333.33 + $charges['employer'], 2);

        $this->assertSame(3000.0, $charges['employee']);
        $this->assertSame(30333.33, $taxable);
        $this->assertSame(1376.67, $irg);
        $this->assertSame(28956.66, $net);
        $this->assertSame(8666.67, $charges['employer']);
        $this->assertSame(42000.0, $cost);
    }
}
