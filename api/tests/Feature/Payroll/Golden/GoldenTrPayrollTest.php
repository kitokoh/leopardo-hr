<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use Tests\TestCase;

/**
 * Golden tests Turquie (TR) — issue #2119, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/TR_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot) : SGK 14 % + chômage 1 % salarial (15 % total), SGK 20,5 % +
 * chômage 2 % patronal (22,5 % total), non plafonnés · IR mensuel = progressif
 * ANNUEL (0-110k 15 %, 110-230k 20 %, 230-580k 27 %, 580k-3M 35 %, >3M 40 %) / 12.
 */
class GoldenTrPayrollTest extends TestCase
{
    private function rules(): TurkeyPayrollRules
    {
        return new TurkeyPayrollRules;
    }

    public function test_golden_tr_smig_20002(): void
    {
        // Calcul manuel, brut = SMIG 20 002 TRY :
        //   SGK+chômage salarial = 20 002 × 15 % = 3 000,30
        //   IR : assiette 17 001,70 → annuel 204 020,40 :
        //     110 000 × 15 % + 94 020,40 × 20 % = 35 304,08 → mensuel 2 942,01
        //   Net = 20 002 − 3 000,30 − 2 942,01 = 14 059,69
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(20002.0);
        $this->assertSame(3000.30, $charges['employee']);
        $this->assertSame(4500.45, $charges['employer']);

        $tax = $rules->calculateIncomeTax(20002.0 - $charges['employee']);
        $this->assertSame(2942.01, $tax);
        $this->assertSame(14059.69, round(20002.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tr_cadre_moyen_60000(): void
    {
        // Calcul manuel, brut 60 000 TRY :
        //   salarial = 9 000,00 · IR : assiette 51 000 → annuel 612 000 :
        //     110 000 × 15 % + 120 000 × 20 % + 350 000 × 27 % + 32 000 × 35 %
        //     = 146 200 → mensuel 12 183,33
        //   Net = 60 000 − 9 000 − 12 183,33 = 38 816,67
        $charges = $this->rules()->calculateSocialCharges(60000.0);
        $this->assertSame(9000.00, $charges['employee']);
        $this->assertSame(13500.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(60000.0 - $charges['employee']);
        $this->assertSame(12183.33, $tax);
        $this->assertSame(38816.67, round(60000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tr_haut_salaire_250000(): void
    {
        // Calcul manuel, brut 250 000 TRY :
        //   salarial = 37 500,00 · IR : assiette 212 500 → annuel 2 550 000 :
        //     110 000 × 15 % + 120 000 × 20 % + 350 000 × 27 % + 1 970 000 × 35 %
        //     = 824 500 → mensuel 68 708,33
        //   Net = 250 000 − 37 500 − 68 708,33 = 143 791,67
        $charges = $this->rules()->calculateSocialCharges(250000.0);
        $this->assertSame(37500.00, $charges['employee']);
        $this->assertSame(56250.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(250000.0 - $charges['employee']);
        $this->assertSame(68708.33, $tax);
        $this->assertSame(143791.67, round(250000.0 - $charges['employee'] - $tax, 2));
    }
}
