<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * Issue #2119 — golden tests France (FR), calculés À LA MAIN (règle #1938).
 * Référence : docs/payroll/FR_COMPLIANCE.md (CGI FR art. 197 — barème
 * 2025, code de la Sécurité sociale, CSG/CRDS).
 *
 * Modèle moteur (FR_COMPLIANCE.md §1-2) :
 *   Sécurité sociale salariale = brut × 7,5 %
 *   CSG = brut × 98,25 % × 9,2 % · CRDS = brut × 98,25 % × 0,5 %
 *   IR  = progressif ANNUEL sur (brut − cotisations) × 12, / 12.
 *   Tranches IR annuelles 2025 = 0–11 294 : 0 % · 11 295–28 797 : 11 % ·
 *      28 798–82 341 : 30 % · 82 342–177 106 : 41 % · > 177 106 : 45 %
 *
 * Écarts documentés (pilot) : pas de quotient familial, de décote, de
 * prélèvement à la source mensualisé ni de plafonds Sécurité sociale
 * (PASS) — voir FR_COMPLIANCE.md.
 */
class GoldenFrPayrollTest extends TestCase
{
    private function rules(): FrancePayrollRules
    {
        return new FrancePayrollRules;
    }

    public function test_golden_fr_smic_1766(): void
    {
        // Calcul manuel (FR_COMPLIANCE.md §1-3), brut = SMIC 1 766 €/mois :
        //   SS = 1 766 × 7,5 % = 132,45
        //   CSG = 1 766 × 98,25 % × 9,2 % = 159,63
        //   CRDS = 1 766 × 98,25 % × 0,5 % = 8,68
        //   Cotisations = 132,45 + 159,63 + 8,68 = 300,75 (arrondi global)
        //   Assiette = 1 465,25 → annuel 17 583,00
        //   IR = (17 583 − 11 294) × 11 % (tranche 11 295–28 797)
        //     = 691,79 → mensuel 57,65
        //   Net = 1 766 − 300,75 − 57,65 = 1 407,60
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(1766.0, $rules);

        $this->assertSame(300.75, $breakdown['social']['employee']);
        $this->assertSame(57.65, $breakdown['income_tax']);
        $this->assertSame(1407.6, $breakdown['net_salary']);
    }

    public function test_golden_fr_cadre_moyen_3500(): void
    {
        // Calcul manuel (FR_COMPLIANCE.md §1), brut 3 500 €/mois :
        //   SS = 262,50 · CSG = 3 500 × 98,25 % × 9,2 % = 316,37
        //   CRDS = 3 500 × 98,25 % × 0,5 % = 17,19
        //   Cotisations = 262,50 + 316,37 + 17,19 = 596,06 (arrondi global)
        //   Assiette = 2 903,94 → annuel 34 847,28
        //   IR progressif = 17 503 × 11 % (11 294–28 797) = 1 925,33
        //     + 6 050,28 × 30 % (28 797–34 847,28) = 1 815,08 → 3 740,41
        //     → mensuel 311,70
        //   Net = 3 500 − 596,06 − 311,70 = 2 592,24
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(3500.0, $rules);

        $this->assertSame(596.06, $breakdown['social']['employee']);
        $this->assertSame(311.7, $breakdown['income_tax']);
        $this->assertSame(2592.24, $breakdown['net_salary']);
    }

    public function test_golden_fr_haut_salaire_8000(): void
    {
        // Calcul manuel (FR_COMPLIANCE.md §1), brut 8 000 €/mois :
        //   SS = 600,00 · CSG = 8 000 × 98,25 % × 9,2 % = 723,12
        //   CRDS = 8 000 × 98,25 % × 0,5 % = 39,30
        //   Cotisations = 600 + 723,12 + 39,30 = 1 362,42
        //   Assiette = 6 637,58 → annuel 79 650,96
        //   IR progressif = 17 503 × 11 % = 1 925,33
        //     + 50 853,96 × 30 % (28 797–79 650,96) = 15 256,19
        //     → 17 181,52 → mensuel 1 431,79
        //   Net = 8 000 − 1 362,42 − 1 431,79 = 5 205,79
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(8000.0, $rules);

        $this->assertSame(1362.42, $breakdown['social']['employee']);
        $this->assertSame(1431.79, $breakdown['income_tax']);
        $this->assertSame(5205.79, $breakdown['net_salary']);
    }
}
