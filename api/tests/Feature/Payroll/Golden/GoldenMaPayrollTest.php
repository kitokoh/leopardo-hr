<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * Issue #2119 — golden tests Maroc (MA), calculés À LA MAIN (jamais dérivés
 * du code — règle #1938). Référence : docs/payroll/MA_COMPLIANCE.md
 * (Code général des impôts MA — IR art. 57-64, CNSS décret 2-77-649,
 * AMO loi 65-00).
 *
 * Modèle moteur (MA_COMPLIANCE.md §1-2) :
 *   CNSS salariale  = min(brut, 6 000) × 4,48 %
 *   AMO salariale    = brut × 2,26 %
 *   IR               = progressif ANNUEL sur (brut − cotisations) × 12,
 *                      déduction forfaitaire par tranche, puis / 12.
 *   Tranches IR annuelles = 0–30 000 : 0 % · 30 001–50 000 : 10 % − 3 000 ·
 *      50 001–60 000 : 20 % − 8 000 · 60 001–80 000 : 30 % − 14 000 ·
 *      80 001–180 000 : 34 % − 17 200 · > 180 000 : 38 % − 24 400
 *
 * Écart documenté (pilot) : l'abattement frais pro 35 % (CGI MA, min 2 500 /
 * max 30 000 MAD/an) n'est pas appliqué par le moteur — voir MA_COMPLIANCE.md.
 */
class GoldenMaPayrollTest extends TestCase
{
    private function rules(): MoroccoPayrollRules
    {
        return new MoroccoPayrollRules;
    }

    public function test_golden_ma_smig_3111(): void
    {
        // Calcul manuel (MA_COMPLIANCE.md §1-3), brut = SMIG 3 111 MAD/mois :
        //   CNSS = 3 111 × 4,48 % = 139,37 (plafond 6 000 non atteint)
        //   AMO  = 3 111 × 2,26 % = 70,31
        //   Cotisations salariales = 209,68
        //   Assiette = 3 111 − 209,68 = 2 901,32 → annuel 34 815,84
        //   IR = 34 815,84 × 10 % − 3 000 (tranche 30 001–50 000)
        //     = 481,584 → mensuel 40,13
        //   Net = 3 111 − 209,68 − 40,13 = 2 861,19
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(3111.0, $rules);

        $this->assertSame(209.68, $breakdown['social']['employee']);
        $this->assertSame(40.13, $breakdown['income_tax']);
        $this->assertSame(2861.19, $breakdown['net_salary']);
    }

    public function test_golden_ma_cadre_moyen_5000(): void
    {
        // Calcul manuel (MA_COMPLIANCE.md §1), brut 5 000 MAD/mois :
        //   CNSS = 5 000 × 4,48 % = 224,00 · AMO = 5 000 × 2,26 % = 113,00
        //   Cotisations = 337,00
        //   Assiette = 4 663 → annuel 55 956,00
        //   IR = 55 956 × 20 % − 8 000 (tranche 50 001–60 000)
        //     = 3 191,20 → mensuel 265,93
        //   Net = 5 000 − 337 − 265,93 = 4 397,07
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(5000.0, $rules);

        $this->assertSame(337.0, $breakdown['social']['employee']);
        $this->assertSame(265.93, $breakdown['income_tax']);
        $this->assertSame(4397.07, $breakdown['net_salary']);
    }

    public function test_golden_ma_haut_salaire_12000(): void
    {
        // Calcul manuel (MA_COMPLIANCE.md §1), brut 12 000 MAD/mois :
        //   CNSS = min(12 000, 6 000) × 4,48 % = 268,80 (plafond atteint)
        //   AMO  = 12 000 × 2,26 % = 271,20
        //   Cotisations = 540,00
        //   Assiette = 11 460 → annuel 137 520,00
        //   IR = 137 520 × 34 % − 17 200 (tranche 80 001–180 000)
        //     = 29 556,80 → mensuel 2 463,07
        //   Net = 12 000 − 540 − 2 463,07 = 8 996,93
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(12000.0, $rules);

        $this->assertSame(540.0, $breakdown['social']['employee']);
        $this->assertSame(2463.07, $breakdown['income_tax']);
        $this->assertSame(8996.93, $breakdown['net_salary']);
    }
}
