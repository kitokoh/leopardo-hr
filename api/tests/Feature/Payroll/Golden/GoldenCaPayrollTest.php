<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use Tests\TestCase;

/**
 * Golden tests Canada (CA) — issue #2119, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/CA_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot, fédéral) : CPP 5,95 % + assurance-emploi 1,66 % salarial
 * (7,61 % total), CPP 5,95 % + AE 2,32 % patronal (8,27 % total), non plafonnés
 * (plafonds CPP/AE non modélisés) · IR fédéral mensuel = progressif ANNUEL
 * (0-55 867 $ 15 %, 55 868-111 733 $ 20,5 %, 111 734-173 205 $ 26 %,
 * 173 206-246 752 $ 29 %, >246 753 $ 33 %) / 12.
 */
class GoldenCaPayrollTest extends TestCase
{
    private function rules(): CanadaPayrollRules
    {
        return new CanadaPayrollRules;
    }

    public function test_golden_ca_smig_2999(): void
    {
        // Calcul manuel, brut = SMIG 2 999 CAD/mois :
        //   CPP+AE salarial = 2 999 × 7,61 % = 228,22
        //   IR : assiette 2 770,78 → annuel 33 249,36 → tranche 15 % :
        //     33 249,36 × 15 % = 4 987,40 → mensuel 415,62
        //   Net = 2 999 − 228,22 − 415,62 = 2 355,16
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(2999.0);
        $this->assertSame(228.22, $charges['employee']);
        $this->assertSame(248.02, $charges['employer']);

        $tax = $rules->calculateIncomeTax(2999.0 - $charges['employee']);
        $this->assertSame(415.62, $tax);
        $this->assertSame(2355.16, round(2999.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_cadre_moyen_6000(): void
    {
        // Calcul manuel, brut 6 000 CAD :
        //   salarial = 456,60 · IR : assiette 5 543,40 → annuel 66 520,80 :
        //     55 867 × 15 % + 10 653,80 × 20,5 % = 10 564,08 → mensuel 880,34
        //   Net = 6 000 − 456,60 − 880,34 = 4 663,06
        $charges = $this->rules()->calculateSocialCharges(6000.0);
        $this->assertSame(456.60, $charges['employee']);
        $this->assertSame(496.20, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(6000.0 - $charges['employee']);
        $this->assertSame(880.34, $tax);
        $this->assertSame(4663.06, round(6000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_haut_salaire_20000(): void
    {
        // Calcul manuel, brut 20 000 CAD :
        //   salarial = 1 522,00 · IR : assiette 18 478 → annuel 221 736 :
        //     55 867 × 15 % + 55 866 × 20,5 % + 61 472 × 26 % + 48 531 × 29 %
        //     = 49 889,29 → mensuel 4 157,44
        //   Net = 20 000 − 1 522 − 4 157,44 = 14 320,56
        $charges = $this->rules()->calculateSocialCharges(20000.0);
        $this->assertSame(1522.00, $charges['employee']);
        $this->assertSame(1654.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(20000.0 - $charges['employee']);
        $this->assertSame(4157.44, $tax);
        $this->assertSame(14320.56, round(20000.0 - $charges['employee'] - $tax, 2));
    }
}
