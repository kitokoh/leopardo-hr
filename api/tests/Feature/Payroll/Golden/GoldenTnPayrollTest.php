<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Tests\TestCase;

/**
 * Issue #2119 — golden tests Tunisie (TN), calculés À LA MAIN (règle #1938).
 * Référence : docs/payroll/TN_COMPLIANCE.md (Code de l'IRPP TN — loi
 * 89-114 modifiée, CNSS loi 60-30 modifiée).
 *
 * Modèle moteur (TN_COMPLIANCE.md §1-2) :
 *   CNSS salariale  = brut × 9,18 %
 *   IRPP            = progressif ANNUEL sur (brut − cotisations) × 12, / 12.
 *   Tranches IRPP annuelles = 0–5 000 : 0 % · 5 001–20 000 : 26 % ·
 *      20 001–30 000 : 28 % · 30 001–50 000 : 32 % · > 50 000 : 35 %
 *
 * Écart documenté (pilot) : abattement 10 % (min 1 000 / max 1 500 TND/an,
 * CGI TN art. 39) et déductions familiales non appliqués par le moteur —
 * voir TN_COMPLIANCE.md.
 */
class GoldenTnPayrollTest extends TestCase
{
    private function rules(): TunisiaPayrollRules
    {
        return new TunisiaPayrollRules;
    }

    public function test_golden_tn_smig_480(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1-3), brut = SMIG 480 TND/mois :
        //   CNSS = 480 × 9,18 % = 44,06
        //   Assiette = 435,94 → annuel 5 231,28
        //   IRPP = (5 231,28 − 5 000) × 26 % (tranche 5 001–20 000)
        //     = 60,13 → mensuel 5,01
        //   Net = 480 − 44,06 − 5,01 = 430,93
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(480.0, $rules);

        $this->assertSame(44.06, $breakdown['social']['employee']);
        $this->assertSame(5.01, $breakdown['income_tax']);
        $this->assertSame(430.93, $breakdown['net_salary']);
    }

    public function test_golden_tn_cadre_moyen_1000(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1), brut 1 000 TND/mois :
        //   CNSS = 1 000 × 9,18 % = 91,80
        //   Assiette = 908,20 → annuel 10 898,40
        //   IRPP = (10 898,40 − 5 000) × 26 % = 1 533,58 → mensuel 127,80
        //   Net = 1 000 − 91,80 − 127,80 = 780,40
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(1000.0, $rules);

        $this->assertSame(91.8, $breakdown['social']['employee']);
        $this->assertSame(127.8, $breakdown['income_tax']);
        $this->assertSame(780.4, $breakdown['net_salary']);
    }

    public function test_golden_tn_haut_salaire_3500(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1), brut 3 500 TND/mois :
        //   CNSS = 3 500 × 9,18 % = 321,30
        //   Assiette = 3 178,70 → annuel 38 144,40
        //   IRPP progressif = 15 000 × 26 % (5 001–20 000) = 3 900,00
        //     + 10 000 × 28 % (20 001–30 000) = 2 800,00
        //     + 8 144,40 × 32 % (30 001–38 144,40) = 2 606,21
        //     → 9 306,21 → mensuel 775,52
        //   Net = 3 500 − 321,30 − 775,52 = 2 403,18
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(3500.0, $rules);

        $this->assertSame(321.3, $breakdown['social']['employee']);
        $this->assertSame(775.52, $breakdown['income_tax']);
        $this->assertSame(2403.18, $breakdown['net_salary']);
    }
}
