<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use Tests\TestCase;

/**
 * Golden tests Canada (CA) — issue #2119 + audit 2026 (pack EN #5255),
 * constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/CA_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot, fédéral 2026) :
 *  - CPP 5,95 % sur (min(brut, YMPE mensuel $6 216,67) − exemption $291,67),
 *    CPP2 4 % sur la tranche [$6 216,67, $7 083,33], EI 1,63 % salarial
 *    (2,282 % patronal) sur le brut plafonné à $5 741,67 (MIE mensuelle).
 *  - IR fédéral = progressif ANNUEL (0-58 523 $ 14 %, 58 524-117 045 $ 20,5 %,
 *    117 046-181 440 $ 26 %, 181 441-258 482 $ 29 %, > 258 483 $ 33 %) moins
 *    le crédit BPA (BPA × 14 % ; BPA $16 452, phase-out 181 440 → 258 482
 *    jusqu'à $14 829), le tout / 12.
 */
class GoldenCaPayrollTest extends TestCase
{
    private function rules(): CanadaPayrollRules
    {
        return new CanadaPayrollRules();
    }

    public function test_golden_ca_minimum_wage_federal_3146(): void
    {
        // Brut = minimum fédéral $3 146 :
        //   CPP = 5,95 % × (3 146 − 291,67) = 169,83 · EI = 1,63 % × 3 146 = 51,28
        //   → salarié 221,11 · patron = 169,83 + 2,282 % × 3 146 = 241,62
        //   IR : assiette 2 924,89 → annuel 35 098,68 → 14 % = 4 913,82 − BPA 2 303,28
        //     = 2 610,54 → mensuel 217,54 · Net = 2 924,89 − 217,54 = 2 707,35
        $charges = $this->rules()->calculateSocialCharges(3146.0);
        $this->assertSame(221.11, $charges['employee']);
        $this->assertSame(241.62, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(3146.0 - $charges['employee']);
        $this->assertSame(217.54, $tax);
        $this->assertSame(2707.35, round(3146.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_revenu_bas_bpa_1000(): void
    {
        // Brut $1 000 :
        //   CPP = 5,95 % × 708,33 = 42,15 · EI = 16,30 → salarié 58,45 · patron = 64,97
        //   IR : annuel 11 298,60 × 14 % = 1 581,80 < crédit BPA 2 303,28 → 0
        //   Net = 1 000 − 58,45 = 941,55
        $charges = $this->rules()->calculateSocialCharges(1000.0);
        $this->assertSame(58.45, $charges['employee']);
        $this->assertSame(64.97, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(1000.0 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(941.55, round(1000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_cadre_moyen_6000(): void
    {
        // Brut $6 000 (EI plafonnée à la MIE mensuelle) :
        //   CPP = 5,95 % × 5 708,33 = 339,65 · EI = 1,63 % × 5 741,67 = 93,59
        //   → salarié 433,24 · patron = 339,65 + 2,282 % × 5 741,67 = 470,67
        //   IR : assiette 5 566,76 → annuel 66 801,12 →
        //     8 193,22 + 8 278,12 × 20,5 % = 9 890,23 − 2 303,28 → mensuel 632,25
        //   Net = 5 566,76 − 632,25 = 4 934,51
        $charges = $this->rules()->calculateSocialCharges(6000.0);
        $this->assertSame(433.24, $charges['employee']);
        $this->assertSame(470.67, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(6000.0 - $charges['employee']);
        $this->assertSame(632.25, $tax);
        $this->assertSame(4934.51, round(6000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_at_ei_cap_5741_67(): void
    {
        // Brut = MIE mensuelle exacte $5 741,67 :
        //   CPP = 5,95 % × 5 450,00 = 324,28 · EI = 93,59 → salarié 417,86 · patron = 455,30
        //   IR : assiette 5 323,81 → annuel 63 885,72 →
        //     8 193,22 + 5 362,72 × 20,5 % = 9 292,58 − 2 303,28 → mensuel 582,44
        //   Net = 5 323,81 − 582,44 = 4 741,37
        $charges = $this->rules()->calculateSocialCharges(5741.67);
        $this->assertSame(417.86, $charges['employee']);
        $this->assertSame(455.30, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(5741.67 - $charges['employee']);
        $this->assertSame(582.44, $tax);
        $this->assertSame(4741.37, round(5741.67 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_at_cpp_ympe_6216_67(): void
    {
        // Brut = YMPE mensuel exact $6 216,67 (CPP max, CPP2 non atteint) :
        //   CPP = 5,95 % × 5 925,00 = 352,54 · EI plafonnée MIE = 1,63 % × 5 741,67 = 93,59
        //   → salarié 446,13 · patron = 352,54 + 2,282 % × 5 741,67 = 483,56
        //   IR : assiette 5 770,54 → annuel 69 246,48 →
        //     8 193,22 + 10 723,48 × 20,5 % = 10 391,53 − 2 303,28 → mensuel 674,02
        //   Net = 5 770,54 − 674,02 = 5 096,52
        $charges = $this->rules()->calculateSocialCharges(6216.67);
        $this->assertSame(446.13, $charges['employee']);
        $this->assertSame(483.56, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(6216.67 - $charges['employee']);
        $this->assertSame(674.02, $tax);
        $this->assertSame(5096.52, round(6216.67 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_cpp2_active_7500(): void
    {
        // Brut $7 500 (CPP2 déclenché entre YMPE et YAMPE) :
        //   CPP = 352,54 · CPP2 = 4 % × 866,66 = 34,67 · EI = 93,59
        //   → salarié 480,79 · patron = 352,54 + 34,67 + 131,02 = 518,23
        //   IR : assiette 7 019,21 → annuel 84 230,52 →
        //     8 193,22 + 25 707,52 × 20,5 % = 13 463,26 − 2 303,28 → mensuel 930,00
        //   Net = 7 019,21 − 930,00 = 6 089,21
        $charges = $this->rules()->calculateSocialCharges(7500.0);
        $this->assertSame(480.79, $charges['employee']);
        $this->assertSame(518.23, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(7500.0 - $charges['employee']);
        $this->assertSame(930.00, $tax);
        $this->assertSame(6089.21, round(7500.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_cadre_superieur_12000(): void
    {
        // Brut $12 000 (toutes cotisations plafonnées) :
        //   CPP = 352,54 · CPP2 = 34,67 · EI = 93,59 → salarié 480,79 · patron = 518,23
        //   IR : assiette 11 519,21 → annuel 138 230,52 →
        //     8 193,22 + 11 997,01 + 21 185,52 × 26 % = 25 698,47 − 2 303,28 → 1 949,60
        //   Net = 11 519,21 − 1 949,60 = 9 569,61
        $charges = $this->rules()->calculateSocialCharges(12000.0);
        $this->assertSame(480.79, $charges['employee']);
        $this->assertSame(518.23, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(12000.0 - $charges['employee']);
        $this->assertSame(1949.60, $tax);
        $this->assertSame(9569.61, round(12000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_bpa_phase_out_20000(): void
    {
        // Brut $20 000 — BPA en phase-out (revenu annuel 234 230,52 ∈ ]181 440, 258 482[) :
        //   BPA = 16 452 − 1 623 × (52 790,52 / 77 042) = 15 339,89 → crédit 2 147,58
        //   IR : assiette 19 519,21 → annuel 234 230,52 →
        //     8 193,22 + 11 997,01 + 16 742,70 + 52 790,52 × 29 % = 52 242,18
        //     − 2 147,58 → mensuel 4 174,55
        //   Net = 19 519,21 − 4 174,55 = 15 344,66
        $charges = $this->rules()->calculateSocialCharges(20000.0);
        $this->assertSame(480.79, $charges['employee']);
        $this->assertSame(518.23, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(20000.0 - $charges['employee']);
        $this->assertSame(4174.55, $tax);
        $this->assertSame(15344.66, round(20000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_tres_haut_salaire_30000(): void
    {
        // Brut $30 000 — BPA au plancher $14 829 (revenu ≥ 258 482) :
        //   salarié 480,79 · patron 518,23 (cotisations plafonnées)
        //   IR : assiette 29 519,21 → annuel 354 230,52 →
        //     8 193,22 + 11 997,01 + 16 742,70 + 22 342,18 + 95 748,52 × 33 %
        //     = 90 872,12 − 2 076,06 → mensuel 7 399,67
        //   Net = 29 519,21 − 7 399,67 = 22 119,54
        $charges = $this->rules()->calculateSocialCharges(30000.0);
        $this->assertSame(480.79, $charges['employee']);
        $this->assertSame(518.23, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(30000.0 - $charges['employee']);
        $this->assertSame(7399.67, $tax);
        $this->assertSame(22119.54, round(30000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_tres_haut_salaire_60000(): void
    {
        // Brut $60 000 :
        //   salarié 480,79 · patron 518,23 (cotisations plafonnées)
        //   IR : assiette 59 519,21 → annuel 714 230,52 →
        //     8 193,22 + 11 997,01 + 16 742,70 + 22 342,18 + 455 748,52 × 33 %
        //     = 241 269,13 − 2 076,06 → mensuel 17 299,67
        //   Net = 59 519,21 − 17 299,67 = 42 219,54
        $charges = $this->rules()->calculateSocialCharges(60000.0);
        $this->assertSame(480.79, $charges['employee']);
        $this->assertSame(518.23, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(60000.0 - $charges['employee']);
        $this->assertSame(17299.67, $tax);
        $this->assertSame(42219.54, round(60000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ca_rules_metadata(): void
    {
        $rules = $this->rules();
        $this->assertSame('CA', $rules->countryCode());
        $this->assertSame('CAD', $rules->currency());
        $this->assertSame('pilot', $rules->confidenceLevel());
        $this->assertSame('en', $rules->language());
        $this->assertSame('America/Toronto', $rules->timezone());
        $this->assertSame([7], $rules->weeklyRestDays());
        $this->assertSame(['monthly'], $rules->supportedPayCycles());
        $this->assertSame(44.0, $rules->overtimeThresholdWeeklyHours());
        $this->assertSame([['up_to_hours' => null, 'multiplier' => 1.5]], $rules->overtimeRateTiers());
        // CLC art. 230 : 1 semaine après 3 mois, puis +1 semaine/an jusqu'à 8.
        $this->assertSame(0.0, $rules->noticePeriodDays(0.1));
        $this->assertSame(7.0, $rules->noticePeriodDays(0.5));
        $this->assertSame(14.0, $rules->noticePeriodDays(1.5));
        $this->assertSame(21.0, $rules->noticePeriodDays(3.5));
        $this->assertSame(56.0, $rules->noticePeriodDays(8.0));
        $this->assertSame(0.2309, $rules->severanceMonthsPerYear(5.0));
        $this->assertNotEmpty($rules->complianceWarning());
        $this->assertSame('docs/payroll/CA_COMPLIANCE.md', $rules->complianceSource());
        $this->assertNull($rules->verificationDate());
        $this->assertCount(5, $rules->legalReferenceTaxSlabs());
        $this->assertCount(6, $rules->socialContributions());
        $this->assertSame(0.0, $rules->calculateBracketTax(5000.0));
        $this->assertFalse($rules->thirteenthMonthMandatory());
        // Province optionnelle (PA2-COUNTRY-009) : timezone + seuil HS.
        $bc = $rules->forProvince('BC');
        $this->assertSame('America/Vancouver', $bc->timezone());
        $this->assertSame(40.0, $bc->overtimeThresholdWeeklyHours());
        $this->assertSame('CA', $bc->countryCode());
    }
}
