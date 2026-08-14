<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Turquie (TR), issue #2119.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/TR_COMPLIANCE.md.
 *
 * NB : barème indicatif (tranches IR 2024 simplifiées) et taux SGK pilotes —
 * pas un substitut à un logiciel certifié (complianceWarning() TR).
 */
class GoldenTrPayrollTest extends TestCase
{
    private function tr(): TurkeyPayrollRules
    {
        return new TurkeyPayrollRules;
    }

    public function test_golden_tr_smig_net(): void
    {
        // Calcul manuel (TR_COMPLIANCE.md §1-§3) — salaire min. 20 002 TRY :
        //   SGK 14 % + chômage 1 % = 15 % × 20 002 = 3 000,30
        //   Assiette IR = 17 001,70 → annuel 204 020,40
        //   Tranches : 0–110 000 × 15 % = 16 500
        //     110 001–204 020,40 : 94 020,40 × 20 % = 18 804,08
        //     Total 35 304,08 → mensuel 2 942,01
        //   Net = 20 002 − 3 000,30 − 2 942,01 = 14 059,69
        $charges = $this->tr()->calculateSocialCharges(20002.0);
        $taxable = 20002.0 - $charges['employee'];

        $this->assertSame(3000.3, $charges['employee']);
        $this->assertSame(4500.45, $charges['employer']);
        $this->assertSame(17001.7, round($taxable, 2));
        $this->assertSame(2942.01, $this->tr()->calculateIncomeTax($taxable));
        $this->assertSame(14059.69, round(20002.0 - $charges['employee'] - $this->tr()->calculateIncomeTax($taxable), 2));
    }

    public function test_golden_tr_cadre_40000(): void
    {
        // Calcul manuel (TR_COMPLIANCE.md §1-§3) — brut 40 000 TRY :
        //   SGK+chômage 15 % × 40 000 = 6 000,00
        //   Assiette = 34 000 → annuel 408 000
        //   Tranches : 0–110 k : 16 500 · 110–230 k : 24 000
        //     230 001–408 000 : 178 000 × 27 % = 48 060
        //     Total 88 560 → mensuel 7 380,00
        $charges = $this->tr()->calculateSocialCharges(40000.0);
        $taxable = 40000.0 - $charges['employee'];

        $this->assertSame(6000.0, $charges['employee']);
        $this->assertSame(9000.0, $charges['employer']);
        $this->assertSame(34000.0, round($taxable, 2));
        $this->assertSame(7380.0, $this->tr()->calculateIncomeTax($taxable));
    }

    public function test_golden_tr_haut_salaire_100000(): void
    {
        // Calcul manuel (TR_COMPLIANCE.md §1-§3) — brut 100 000 TRY :
        //   SGK+chômage 15 % × 100 000 = 15 000,00
        //   Assiette = 85 000 → annuel 1 020 000
        //   Tranches : 16 500 + 24 000 + 350 000 × 27 % = 94 500
        //     + (1 020 000 − 580 000) × 35 % = 154 000 → total 289 000
        //     → mensuel 24 083,33
        $charges = $this->tr()->calculateSocialCharges(100000.0);
        $taxable = 100000.0 - $charges['employee'];

        $this->assertSame(15000.0, $charges['employee']);
        $this->assertSame(22500.0, $charges['employer']);
        $this->assertSame(85000.0, round($taxable, 2));
        $this->assertSame(24083.33, $this->tr()->calculateIncomeTax($taxable));
    }

    public function test_golden_tr_confidence_and_metadata(): void
    {
        $this->assertSame('pilot', $this->tr()->confidenceLevel());
        $this->assertSame('TR', $this->tr()->countryCode());
        $this->assertSame('TRY', $this->tr()->currency());
        $this->assertSame(20002.0, $this->tr()->minimumWage());
        $this->assertStringContainsString('not', strtolower($this->tr()->complianceWarning()));
    }
}
