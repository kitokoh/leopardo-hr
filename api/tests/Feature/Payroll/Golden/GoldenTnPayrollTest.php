<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Tunisie (TN), issues
 * #2119 (verrouillage des valeurs) + #2261 (abattement IRPP 10 % CGI TN
 * art. 39, min 1 000 / max 1 500 TND/an).
 *
 * Méthodologie : chaque valeur attendue est CALCULÉE À LA MAIN dans un
 * commentaire PHP, jamais reprise du code. Référence légale :
 * docs/payroll/TN_COMPLIANCE.md §1-§3.
 */
class GoldenTnPayrollTest extends TestCase
{
    private function tn(): TunisiaPayrollRules
    {
        return new TunisiaPayrollRules;
    }

    public function test_golden_tn_smig_net(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1-§3) — SMIG 480 TND :
        //   CNSS salariale 9,18 % × 480 = 44,06
        //   Assiette IR = 435,94 → annuel 5 231,28
        //   Abattement art. 39 : 10 % = 523,13 < plancher 1 000 → 1 000
        //   Revenu après abattement = 4 231,28 → tranche 0–5 000 → 0 %
        //   IR mensuel = 0 · Net = 480 − 44,06 = 435,94
        $charges = $this->tn()->calculateSocialCharges(480.0);
        $taxable = 480.0 - $charges['employee'];

        $this->assertSame(44.06, $charges['employee']);
        $this->assertSame(79.54, $charges['employer']);
        $this->assertSame(435.94, round($taxable, 2));
        $this->assertSame(0.0, $this->tn()->calculateIncomeTax($taxable));
        $this->assertSame(435.94, round(480.0 - $charges['employee'] - $this->tn()->calculateIncomeTax($taxable), 2));
    }

    public function test_golden_tn_cadre_1000(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1-§3) — brut 1 000 TND :
        //   CNSS 9,18 % × 1 000 = 91,80
        //   Assiette = 908,20 → annuel 10 898,40
        //   Abattement art. 39 : 10 % = 1 089,84 (dans [1 000 ; 1 500])
        //   → revenu imposable 9 808,56
        //   Tranche 5 001–20 000 : (9 808,56 − 5 000) × 26 % = 1 250,23
        //     → mensuel 104,19
        $charges = $this->tn()->calculateSocialCharges(1000.0);
        $taxable = 1000.0 - $charges['employee'];

        $this->assertSame(91.8, $charges['employee']);
        $this->assertSame(165.7, $charges['employer']);
        $this->assertSame(908.2, round($taxable, 2));
        $this->assertSame(104.19, $this->tn()->calculateIncomeTax($taxable));
    }

    public function test_golden_tn_haut_salaire_3500(): void
    {
        // Calcul manuel (TN_COMPLIANCE.md §1-§3) — brut 3 500 TND :
        //   CNSS 9,18 % × 3 500 = 321,30
        //   Assiette = 3 178,70 → annuel 38 144,40
        //   Abattement art. 39 : 10 % = 3 814,44 > plafond 1 500 → 1 500
        //   → revenu imposable 36 644,40
        //   Tranches : 5 001–20 000 : 15 000 × 26 % = 3 900
        //     20 001–30 000 : 10 000 × 28 % = 2 800
        //     30 001–36 644,40 : 6 644,40 × 32 % = 2 126,21
        //     Total 8 826,21 → mensuel 735,52
        $charges = $this->tn()->calculateSocialCharges(3500.0);
        $taxable = 3500.0 - $charges['employee'];

        $this->assertSame(321.3, $charges['employee']);
        $this->assertSame(579.95, $charges['employer']);
        $this->assertSame(3178.7, round($taxable, 2));
        $this->assertSame(735.52, $this->tn()->calculateIncomeTax($taxable));
    }

    public function test_golden_tn_abatement_dedicated_method(): void
    {
        // Issue #2261 — méthode DÉDIÉE (constitution §III), bornes art. 39.
        $rules = $this->tn();

        // Revenu annuel 5 000 → abattement 500 < plancher → 1 000.
        $this->assertSame(4000.0, $rules->applyAnnualAbatement(5000.0));
        // Revenu annuel 12 000 → abattement 1 200 (10 % dans les bornes).
        $this->assertSame(10800.0, $rules->applyAnnualAbatement(12000.0));
        // Revenu annuel 40 000 → abattement 4 000 > plafond → 1 500.
        $this->assertSame(38500.0, $rules->applyAnnualAbatement(40000.0));
    }

    public function test_golden_tn_confidence_and_metadata(): void
    {
        $this->assertSame('pilot', $this->tn()->confidenceLevel());
        $this->assertSame('TN', $this->tn()->countryCode());
        $this->assertSame('TND', $this->tn()->currency());
        $this->assertSame(480.0, $this->tn()->minimumWage());
    }
}
