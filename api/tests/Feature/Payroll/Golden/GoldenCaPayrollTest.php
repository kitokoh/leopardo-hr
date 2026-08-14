<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Canada (CA), issue #2119.
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/CA_COMPLIANCE.md.
 *
 * NB : barème fédéral indicatif + taux CPP/EI pilotes ; l'impôt provincial
 * n'est pas modélisé (confidenceLevel() = 'placeholder' — CA_COMPLIANCE.md).
 */
class GoldenCaPayrollTest extends TestCase
{
    private function ca(): CanadaPayrollRules
    {
        return new CanadaPayrollRules;
    }

    public function test_golden_ca_smig_net(): void
    {
        // Calcul manuel (CA_COMPLIANCE.md §1-§3) — salaire min. réf. 2 999 CAD :
        //   CPP 5,95 % + EI 1,66 % = 7,61 % × 2 999 = 228,22
        //   Assiette IR = 2 770,78 → annuel 33 249,36
        //   Tranche 0–55 867 : 33 249,36 × 15 % = 4 987,40 → mensuel 415,62
        //   Net = 2 999 − 228,22 − 415,62 = 2 355,16
        $charges = $this->ca()->calculateSocialCharges(2999.0);
        $taxable = 2999.0 - $charges['employee'];

        $this->assertSame(228.22, $charges['employee']);
        $this->assertSame(248.02, $charges['employer']);
        $this->assertSame(2770.78, round($taxable, 2));
        $this->assertSame(415.62, $this->ca()->calculateIncomeTax($taxable));
        $this->assertSame(2355.16, round(2999.0 - $charges['employee'] - $this->ca()->calculateIncomeTax($taxable), 2));
    }

    public function test_golden_ca_cadre_8000(): void
    {
        // Calcul manuel (CA_COMPLIANCE.md §1-§3) — brut 8 000 CAD :
        //   CPP+EI 7,61 % × 8 000 = 608,80
        //   Assiette = 7 391,20 → annuel 88 694,40
        //   Tranches : 0–55 867 : 8 380,05 · 55 868–88 694,40 :
        //     32 827,40 × 20,5 % = 6 729,62 → total 15 109,67 → mensuel 1 259,14
        $charges = $this->ca()->calculateSocialCharges(8000.0);
        $taxable = 8000.0 - $charges['employee'];

        $this->assertSame(608.8, $charges['employee']);
        $this->assertSame(661.6, $charges['employer']);
        $this->assertSame(7391.2, round($taxable, 2));
        $this->assertSame(1259.14, $this->ca()->calculateIncomeTax($taxable));
    }

    public function test_golden_ca_haut_salaire_20000(): void
    {
        // Calcul manuel (CA_COMPLIANCE.md §1-§3) — brut 20 000 CAD :
        //   CPP+EI 7,61 % × 20 000 = 1 522,00
        //   Assiette = 18 478 → annuel 221 736
        //   Tranches : 0–55 867 : 8 380,05 · 55 868–111 733 : 11 452,53
        //     111 734–173 205 : 15 982,72 · 173 206–221 736 :
        //     48 531 × 29 % = 14 073,99 → total 49 889,29 → mensuel 4 157,44
        $charges = $this->ca()->calculateSocialCharges(20000.0);
        $taxable = 20000.0 - $charges['employee'];

        $this->assertSame(1522.0, $charges['employee']);
        $this->assertSame(1654.0, $charges['employer']);
        $this->assertSame(18478.0, round($taxable, 2));
        $this->assertSame(4157.44, $this->ca()->calculateIncomeTax($taxable));
    }

    public function test_golden_ca_confidence_and_metadata(): void
    {
        $this->assertSame('placeholder', $this->ca()->confidenceLevel());
        $this->assertSame('CA', $this->ca()->countryCode());
        $this->assertSame('CAD', $this->ca()->currency());
        $this->assertSame(2999.0, $this->ca()->minimumWage());
    }
}
