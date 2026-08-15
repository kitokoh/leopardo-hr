<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use Tests\TestCase;

/**
 * Golden tests Tunisie (TN) — issue #2119, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/TN_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot) : CNSS 9,18 % / 16,57 % non plafonnée · IR mensuel = progressif
 * ANNUEL (0-5k 0 %, 5-20k 26 %, 20-30k 28 %, 30-50k 32 %, >50k 35 %) / 12,
 * assiette = brut − CNSS.
 */
class GoldenTnPayrollTest extends TestCase
{
    private function rules(): TunisiaPayrollRules
    {
        return new TunisiaPayrollRules();
    }

    public function test_golden_tn_smig_480(): void
    {
        // Calcul manuel, brut = SMIG 480 TND :
        //   CNSS salariale = 480 × 9,18 % = 44,06
        //   IR : assiette 435,94 → annuel 5 231,28 → tranche 26 % :
        //     (5 231,28 − 5 000) × 26 % = 60,13 → mensuel 5,01
        //   Net = 480 − 44,06 − 5,01 = 430,93
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(480.0);
        $this->assertSame(44.06, $charges['employee']);
        $this->assertSame(79.54, $charges['employer']);

        $tax = $rules->calculateIncomeTax(480.0 - $charges['employee']);
        $this->assertSame(5.01, $tax);
        $this->assertSame(430.93, round(480.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tn_cadre_moyen_2000(): void
    {
        // Calcul manuel, brut 2 000 TND :
        //   CNSS = 183,60 · IR : assiette 1 816,40 → annuel 21 796,80 :
        //     15 000 × 26 % + 1 796,80 × 28 % = 4 403,10 → mensuel 366,93
        //   Net = 2 000 − 183,60 − 366,93 = 1 449,47
        $charges = $this->rules()->calculateSocialCharges(2000.0);
        $this->assertSame(183.60, $charges['employee']);
        $this->assertSame(331.40, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(2000.0 - $charges['employee']);
        $this->assertSame(366.93, $tax);
        $this->assertSame(1449.47, round(2000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tn_haut_salaire_8000(): void
    {
        // Calcul manuel, brut 8 000 TND :
        //   CNSS = 734,40 · IR : assiette 7 265,60 → annuel 87 187,20 :
        //     15 000 × 26 % + 10 000 × 28 % + 20 000 × 32 % + 37 187,20 × 35 %
        //     = 26 115,52 → mensuel 2 176,29
        //   Net = 8 000 − 734,40 − 2 176,29 = 5 089,31
        $charges = $this->rules()->calculateSocialCharges(8000.0);
        $this->assertSame(734.40, $charges['employee']);
        $this->assertSame(1325.60, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(8000.0 - $charges['employee']);
        $this->assertSame(2176.29, $tax);
        $this->assertSame(5089.31, round(8000.0 - $charges['employee'] - $tax, 2));
    }
}
