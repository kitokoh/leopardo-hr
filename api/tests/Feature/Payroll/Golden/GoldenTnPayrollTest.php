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
        // Calcul manuel, brut = SMIG 480 TND (CGI TN art. 39 — abattement
        // 10 % du revenu annuel imposable, plancher 1 000 / plafond 1 500 TND/an,
        // appliqué AVANT le barème progressif) :
        //   CNSS salariale = 480 × 9,18 % = 44,06
        //   IR : assiette 435,94 → annuel 5 231,28 → abattement 10 % = 523,13
        //     < plancher → abattement 1 000 → imposable 4 231,28 (< 5 000,
        //     tranche 0 %) → mensuel 0,00
        //   Net = 480 − 44,06 − 0,00 = 435,94
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(480.0);
        $this->assertSame(44.06, $charges['employee']);
        $this->assertSame(79.54, $charges['employer']);

        $tax = $rules->calculateIncomeTax(480.0 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(435.94, round(480.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tn_cadre_moyen_2000(): void
    {
        // Calcul manuel, brut 2 000 TND (abattement CGI TN art. 39) :
        //   CNSS = 183,60 · IR : assiette 1 816,40 → annuel 21 796,80 :
        //     abattement 10 % = 2 179,68 > plafond → 1 500 → imposable 20 296,80
        //     15 000 × 26 % + 296,80 × 28 % = 3 983,10 → mensuel 331,93
        //   Net = 2 000 − 183,60 − 331,93 = 1 484,47
        $charges = $this->rules()->calculateSocialCharges(2000.0);
        $this->assertSame(183.60, $charges['employee']);
        $this->assertSame(331.40, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(2000.0 - $charges['employee']);
        $this->assertSame(331.93, $tax);
        $this->assertSame(1484.47, round(2000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tn_haut_salaire_8000(): void
    {
        // Calcul manuel, brut 8 000 TND (abattement CGI TN art. 39) :
        //   CNSS = 734,40 · IR : assiette 7 265,60 → annuel 87 187,20 :
        //     abattement 10 % = 8 718,72 > plafond → 1 500 → imposable 85 687,20
        //     15 000 × 26 % + 10 000 × 28 % + 20 000 × 32 % + 35 687,20 × 35 %
        //     = 25 590,52 → mensuel 2 132,54
        //   Net = 8 000 − 734,40 − 2 132,54 = 5 133,06
        $charges = $this->rules()->calculateSocialCharges(8000.0);
        $this->assertSame(734.40, $charges['employee']);
        $this->assertSame(1325.60, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(8000.0 - $charges['employee']);
        $this->assertSame(2132.54, $tax);
        $this->assertSame(5133.06, round(8000.0 - $charges['employee'] - $tax, 2));
    }
}
