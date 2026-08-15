<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use Tests\TestCase;

/**
 * Golden tests Maroc (MA) — issues #2119/#2260, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN
 * (docs/payroll/MA_COMPLIANCE.md), pas reprise du code — une divergence =
 * régression de conformité.
 *
 * Règles (pilot) : CNSS 4,48 % / 8,98 % plafonnée 6 000 MAD · AMO 2,26 % /
 * 4,11 % non plafonnée · abattement frais professionnels 35 % du brut
 * (CGI MA art. 58, min 2 500 / max 30 000 MAD/an — #2260) appliqué AVANT
 * le barème · IR mensuel = progressif ANNUEL (tranches 0-30k 0 %,
 * 30-50k 10 %, 50-60k 20 %, 60-80k 30 %, 80-180k 34 %, >180k 38 %) / 12,
 * assiette = brut − CNSS − AMO.
 */
class GoldenMaPayrollTest extends TestCase
{
    private function rules(): MoroccoPayrollRules
    {
        return new MoroccoPayrollRules;
    }

    public function test_golden_ma_smig_3111(): void
    {
        // Calcul manuel, brut = SMIG 3 111 MAD :
        //   CNSS salariale = min(3 111, 6 000) × 4,48 % = 139,37
        //   AMO salariale  = 3 111 × 2,26 % = 70,31 → total salarié 209,68
        //   Abattement frais pro = 3 111 × 35 % = 1 088,85 (bornes
        //     208,33–2 500 MAD/mois respectées) → assiette IR :
        //     2 901,32 − 1 088,85 = 1 812,47 → annuel 21 749,64 → tranche 0 %
        //     → IR = 0 (avant #2260 : 40,13 — sur-imposition du SMIG)
        //   Net = 3 111 − 209,68 − 0 = 2 901,32
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(3111.0);
        $this->assertSame(209.68, $charges['employee']);
        $this->assertSame(407.23, $charges['employer']); // 279,37 CNSS + 127,86 AMO

        $tax = $rules->calculateIncomeTax(3111.0 - $charges['employee'], 12, 3111.0);
        $this->assertSame(0.0, $tax);
        $this->assertSame(2901.32, round(3111.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ma_cadre_moyen_10000(): void
    {
        // Calcul manuel, brut 10 000 MAD :
        //   CNSS = 6 000 × 4,48 % = 268,80 · AMO = 226,00 → salarié 494,80
        //   Abattement = 10 000 × 35 % = 3 500 → plafonné à 2 500/mois
        //   → assiette IR : 9 505,20 − 2 500 = 7 005,20 → annuel 84 062,40
        //   → tranche 34 % : (84 062,40 × 34 %) − 17 200 = 11 381,22
        //   → mensuel 948,43 (avant #2260 : 1 798,43)
        //   Net = 10 000 − 494,80 − 948,43 = 8 556,77
        $charges = $this->rules()->calculateSocialCharges(10000.0);
        $this->assertSame(494.80, $charges['employee']);
        $this->assertSame(949.80, $charges['employer']); // 538,80 CNSS + 411,00 AMO

        $tax = $this->rules()->calculateIncomeTax(10000.0 - $charges['employee'], 12, 10000.0);
        $this->assertSame(948.43, $tax);
        $this->assertSame(8556.77, round(10000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ma_haut_salaire_60000(): void
    {
        // Calcul manuel, brut 60 000 MAD :
        //   CNSS = 6 000 × 4,48 % = 268,80 (plafond) · AMO = 1 356,00
        //   → salarié 1 624,80
        //   Abattement = 60 000 × 35 % = 21 000 → plafonné à 2 500/mois
        //   → assiette IR : 58 375,20 − 2 500 = 55 875,20 → annuel 670 502,40
        //   → tranche 38 % : (670 502,40 × 38 %) − 24 400 = 230 390,91
        //   → mensuel 19 199,24 (avant #2260 : 20 149,24)
        //   Net = 60 000 − 1 624,80 − 19 199,24 = 39 175,96
        $charges = $this->rules()->calculateSocialCharges(60000.0);
        $this->assertSame(1624.80, $charges['employee']);
        $this->assertSame(3004.80, $charges['employer']); // 538,80 CNSS + 2 466,00 AMO

        $tax = $this->rules()->calculateIncomeTax(60000.0 - $charges['employee'], 12, 60000.0);
        $this->assertSame(19199.24, $tax);
        $this->assertSame(39175.96, round(60000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ma_abattement_bornes(): void
    {
        $rules = $this->rules();

        // Plancher : brut très faible → 35 % < 208,33 → borne basse appliquée.
        // 500 MAD : abattement = max(208,33 ; 175) = 208,33
        $chargesLow = $rules->calculateSocialCharges(500.0);
        $taxLow = $rules->calculateIncomeTax(500.0 - $chargesLow['employee'], 12, 500.0);
        $this->assertSame(0.0, $taxLow); // assiette 500−33,70−208,33 → annuel < 30 000

        // Plafond : brut élevé → 35 % > 2 500 → borne haute appliquée.
        $chargesHigh = $rules->calculateSocialCharges(200000.0);
        $taxHigh = $rules->calculateIncomeTax(200000.0 - $chargesHigh['employee'], 12, 200000.0);
        // salarié = 268,80 (CNSS plafonnée 6 000) + 4 520,00 (AMO 2,26 % non
        // plafonnée) = 4 788,80 → assiette = 200 000 − 4 788,80 − 2 500
        // = 192 711,20 → annuel 2 312 534,40 → tranche 38 % :
        // (× 38 %) − 24 400 = 854 363,07 → mensuel 71 196,92
        $this->assertSame(71196.92, $taxHigh);
    }

    public function test_golden_ma_declares_deduction_shape(): void
    {
        // CGI MA art. 58 : 35 %, min 2 500 MAD/an, max 30 000 MAD/an.
        $this->assertSame(['rate' => 35.0, 'cap' => 2500.0, 'min' => 208.33], $this->rules()->professionalExpensesDeduction());
    }
}
