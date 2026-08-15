<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use Tests\TestCase;

/**
 * Golden tests France (FR) — issue #2119, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/FR_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot) : SS salariale 7,5 % / patronale 30 % · CSG 9,2 % + CRDS 0,5 %
 * sur base 98,25 % du brut · IR mensuel = progressif ANNUEL (0-11 294 € 0 %,
 * 11 295-28 797 € 11 %, 28 798-82 341 € 30 %, 82 342-177 106 € 41 %, >177 107 € 45 %) / 12.
 */
class GoldenFrPayrollTest extends TestCase
{
    private function rules(): FrancePayrollRules
    {
        return new FrancePayrollRules();
    }

    public function test_golden_fr_smig_1766(): void
    {
        // Calcul manuel, brut = SMIG 1 766 € :
        //   SS = 132,45 · CSG = 1 735,095 × 9,2 % = 159,63 · CRDS = 8,68
        //   → salarié 300,75 (arrondi sur la somme)
        //   IR : assiette 1 465,25 → annuel 17 583 → tranche 11 % :
        //     (17 583 − 11 294) × 11 % = 691,79 → mensuel 57,65
        //   Net = 1 766 − 300,75 − 57,65 = 1 407,60
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(1766.0);
        $this->assertSame(300.75, $charges['employee']);
        $this->assertSame(529.80, $charges['employer']);

        $tax = $rules->calculateIncomeTax(1766.0 - $charges['employee']);
        $this->assertSame(57.65, $tax);
        $this->assertSame(1407.60, round(1766.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_cadre_moyen_4000(): void
    {
        // Calcul manuel, brut 4 000 € :
        //   SS = 300,00 · CSG = 3 930 × 9,2 % = 361,56 · CRDS = 19,65 → salarié 681,21
        //   IR : assiette 3 318,79 → annuel 39 825,48 :
        //     17 503 × 11 % + 11 028,48 × 30 % = 5 233,87 → mensuel 436,16
        //   Net = 4 000 − 681,21 − 436,16 = 2 882,63
        $charges = $this->rules()->calculateSocialCharges(4000.0);
        $this->assertSame(681.21, $charges['employee']);
        $this->assertSame(1200.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(4000.0 - $charges['employee']);
        $this->assertSame(436.16, $tax);
        $this->assertSame(2882.63, round(4000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_haut_salaire_15000(): void
    {
        // Calcul manuel, brut 15 000 € :
        //   SS = 1 125,00 · CSG = 14 737,50 × 9,2 % = 1 355,85 · CRDS = 73,69
        //   → salarié 2 554,54
        //   IR : assiette 12 445,46 → annuel 149 345,52 :
        //     17 503 × 11 % + 53 544 × 30 % + 67 004,52 × 41 % = 45 460,38
        //     → mensuel 3 788,37
        //   Net = 15 000 − 2 554,54 − 3 788,37 = 8 657,09
        $charges = $this->rules()->calculateSocialCharges(15000.0);
        $this->assertSame(2554.54, $charges['employee']);
        $this->assertSame(4500.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(15000.0 - $charges['employee']);
        $this->assertSame(3788.37, $tax);
        $this->assertSame(8657.09, round(15000.0 - $charges['employee'] - $tax, 2));
    }
}
