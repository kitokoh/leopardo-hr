<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use Tests\TestCase;

/**
 * Golden tests Maroc (MA) — issue #2119, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/MA_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot) : CNSS 4,48 % / 8,98 % plafonnée 6 000 MAD · AMO 2,26 % / 4,11 %
 * non plafonnée · IR mensuel = progressif ANNUEL (tranches 0-30k 0 %, 30-50k 10 %,
 * 50-60k 20 %, 60-80k 30 %, 80-180k 34 %, >180k 38 %) / 12, assiette = brut − CNSS.
 */
class GoldenMaPayrollTest extends TestCase
{
    private function rules(): MoroccoPayrollRules
    {
        return new MoroccoPayrollRules();
    }

    public function test_golden_ma_smig_3111(): void
    {
        // Calcul manuel, brut = SMIG 3 111 MAD :
        //   CNSS salariale = min(3 111, 6 000) × 4,48 % = 139,37
        //   AMO salariale  = 3 111 × 2,26 % = 70,31 → total salarié 209,68
        //   Abattement frais pro (CGI MA art. 58, #2260) : 35 % du brut ANNUEL
        //     = 35 % × (2 901,32 × 12) = 12 185,54 (plancher 2 500 < OK < plafond
        //     30 000) → assiette annuelle 34 815,84 − 12 185,54 = 22 630,30
        //     → tranche 0 % → IR mensuel 0,00
        //   Net = 3 111 − 209,68 − 0,00 = 2 901,32
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(3111.0);
        $this->assertSame(209.68, $charges['employee']);
        $this->assertSame(407.23, $charges['employer']); // 279,37 CNSS + 127,86 AMO

        $tax = $rules->calculateIncomeTax(3111.0 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(2901.32, round(3111.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ma_cadre_moyen_10000(): void
    {
        // Calcul manuel, brut 10 000 MAD :
        //   CNSS = 6 000 × 4,48 % = 268,80 · AMO = 226,00 → salarié 494,80
        //   Abattement frais pro (CGI MA art. 58, #2260) : 35 % du brut ANNUEL
        //     = 35 % × (9 505,20 × 12) = 39 921,84 → plafonné à 30 000/an
        //     → assiette annuelle 114 062,40 − 30 000 = 84 062,40
        //     → tranche 34 % (fixe 17 200) : 84 062,40 × 34 % − 17 200
        //     = 11 381,22 → mensuel 948,43 (arrondi somme)
        $charges = $this->rules()->calculateSocialCharges(10000.0);
        $this->assertSame(494.80, $charges['employee']);
        $this->assertSame(949.80, $charges['employer']); // 538,80 CNSS + 411,00 AMO

        $tax = $this->rules()->calculateIncomeTax(10000.0 - $charges['employee']);
        $this->assertSame(948.43, $tax);
        $this->assertSame(8556.77, round(10000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ma_haut_salaire_60000(): void
    {
        // Calcul manuel, brut 60 000 MAD :
        //   CNSS = 6 000 × 4,48 % = 268,80 (plafond) · AMO = 1 356,00 → salarié 1 624,80
        //   Abattement frais pro (CGI MA art. 58, #2260) : 35 % du brut ANNUEL
        //     = 35 % × (58 375,20 × 12) = 245 175,84 → plafonné à 30 000/an
        //     → assiette annuelle 700 502,40 − 30 000 = 670 502,40
        //     → tranche 38 % (fixe 24 400) : 670 502,40 × 38 % − 24 400
        //     = 230 390,91 → mensuel 19 199,24
        $charges = $this->rules()->calculateSocialCharges(60000.0);
        $this->assertSame(1624.80, $charges['employee']);
        $this->assertSame(3004.80, $charges['employer']); // 538,80 CNSS + 2 466,00 AMO

        $tax = $this->rules()->calculateIncomeTax(60000.0 - $charges['employee']);
        $this->assertSame(19199.24, $tax);
        $this->assertSame(39175.96, round(60000.0 - $charges['employee'] - $tax, 2));
    }
}
