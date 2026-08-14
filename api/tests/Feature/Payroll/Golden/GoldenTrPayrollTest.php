<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * Issue #2119 — golden tests Turquie (TR), calculés À LA MAIN (règle #1938).
 * Référence : docs/payroll/TR_COMPLIANCE.md (Gelir Vergisi Kanunu — loi
 * n° 193, barème 2024, loi SGK n° 5510, loi chômage n° 4447).
 *
 * Modèle moteur (TR_COMPLIANCE.md §1-2) :
 *   SGK salariale   = brut × 14 %
 *   Chômage salarial = brut × 1 %
 *   IR              = progressif ANNUEL sur (brut − cotisations) × 12, / 12.
 *   Tranches IR annuelles 2024 = 0–110 000 : 15 % · 110 001–230 000 : 20 % ·
 *      230 001–580 000 : 27 % · 580 001–3 000 000 : 35 % · > 3 000 000 : 40 %
 *
 * Écart documenté (pilot) : pas d'exonération IR du salaire minimum (2022+)
 * ni de déduction forfaitaire — voir TR_COMPLIANCE.md.
 */
class GoldenTrPayrollTest extends TestCase
{
    private function rules(): TurkeyPayrollRules
    {
        return new TurkeyPayrollRules;
    }

    public function test_golden_tr_salaire_minimum_20002(): void
    {
        // Calcul manuel (TR_COMPLIANCE.md §1-3), brut = min. légal
        // 20 002 TRY/mois :
        //   Cotisations = 20 002 × (14 % + 1 %) = 3 000,30
        //   Assiette = 17 001,70 → annuel 204 020,40
        //   IR progressif = 110 000 × 15 % = 16 500,00
        //     + 94 020,40 × 20 % (110 001–204 020,40) = 18 804,08
        //     → 35 304,08 → mensuel 2 942,01
        //   Net = 20 002 − 3 000,30 − 2 942,01 = 14 059,69
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(20002.0, $rules);

        $this->assertSame(3000.3, $breakdown['social']['employee']);
        $this->assertSame(2942.01, $breakdown['income_tax']);
        $this->assertSame(14059.69, $breakdown['net_salary']);
    }

    public function test_golden_tr_cadre_moyen_45000(): void
    {
        // Calcul manuel (TR_COMPLIANCE.md §1), brut 45 000 TRY/mois :
        //   Cotisations = 45 000 × 15 % = 6 750,00
        //   Assiette = 38 250 → annuel 459 000,00
        //   IR progressif = 16 500 + 120 000 × 20 % (110 001–230 000)
        //     = 24 000 + 229 000 × 27 % (230 001–459 000) = 61 830
        //     → 102 330,00 → mensuel 8 527,50
        //   Net = 45 000 − 6 750 − 8 527,50 = 29 722,50
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(45000.0, $rules);

        $this->assertSame(6750.0, $breakdown['social']['employee']);
        $this->assertSame(8527.5, $breakdown['income_tax']);
        $this->assertSame(29722.5, $breakdown['net_salary']);
    }

    public function test_golden_tr_haut_salaire_120000(): void
    {
        // Calcul manuel (TR_COMPLIANCE.md §1), brut 120 000 TRY/mois :
        //   Cotisations = 120 000 × 15 % = 18 000,00
        //   Assiette = 102 000 → annuel 1 224 000,00
        //   IR progressif = 16 500 + 24 000 + 350 000 × 27 %
        //     (230 001–580 000) = 94 500 + 644 000 × 35 %
        //     (580 001–1 224 000) = 225 400 → 360 400,00
        //     → mensuel 30 033,33
        //   Net = 120 000 − 18 000 − 30 033,33 = 71 966,67
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(120000.0, $rules);

        $this->assertSame(18000.0, $breakdown['social']['employee']);
        $this->assertSame(30033.33, $breakdown['income_tax']);
        $this->assertSame(71966.67, $breakdown['net_salary']);
    }
}
