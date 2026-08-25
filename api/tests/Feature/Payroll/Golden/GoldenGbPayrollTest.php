<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\UnitedKingdomPayrollRules;
use Tests\TestCase;

/**
 * Golden tests Royaume-Uni (GB) — pack EN #5255, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/GB_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot, 2026-27) :
 *  - NI Class 1 employé : 8 % entre le PT mensuel £1 047,50 et l'UEL mensuel
 *    £4 189,17, puis 2 % au-delà ; employeur : 15 % au-delà du ST mensuel
 *    £416,67.
 *  - PAYE : personal allowance £12 570 (tranche 0 %), 20 % jusqu'à £50 270,
 *    40 % jusqu'à £125 140, 45 % au-delà (barème ANNUEL / 12).
 *  - Aucun plafond annuel NI (cumul mensuel simple), pas de pension
 *    auto-enrolment modélisée.
 */
class GoldenGbPayrollTest extends TestCase
{
    private function rules(): UnitedKingdomPayrollRules
    {
        return new UnitedKingdomPayrollRules;
    }

    public function test_golden_gb_national_living_wage_2203(): void
    {
        // Brut = NLW mensuel £2 203,00 :
        //   NI sal. = 8 % × (2 203 − 1 047,50) = 92,44 · NI pat. = 15 % × 1 786,33 = 267,95
        //   IR : assiette 2 110,56 → annuel 25 326,72 → (25 326,72 − 12 570) × 20 % = 2 551,34
        //     → mensuel 212,61 · Net = 2 203 − 92,44 − 212,61 = 1 897,95
        $charges = $this->rules()->calculateSocialCharges(2203.0);
        $this->assertSame(92.44, $charges['employee']);
        $this->assertSame(267.95, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(2203.0 - $charges['employee']);
        $this->assertSame(212.61, $tax);
        $this->assertSame(1897.95, round(2203.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_cadre_moyen_5000(): void
    {
        // Brut £5 000 :
        //   NI sal. = 8 % × 3 141,67 + 2 % × 810,83 = 251,33 + 16,22 = 267,55
        //   NI pat. = 15 % × 4 583,33 = 687,50
        //   IR : assiette 4 732,45 → annuel 56 789,40 →
        //     37 700 × 20 % + 6 519,40 × 40 % = 10 147,76 → mensuel 845,65
        //   Net = 5 000 − 267,55 − 845,65 = 3 886,80
        $charges = $this->rules()->calculateSocialCharges(5000.0);
        $this->assertSame(267.55, $charges['employee']);
        $this->assertSame(687.50, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(5000.0 - $charges['employee']);
        $this->assertSame(845.65, $tax);
        $this->assertSame(3886.80, round(5000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_haut_salaire_12000(): void
    {
        // Brut £12 000 (tranche additionnelle 45 %) :
        //   NI sal. = 8 % × 3 141,67 + 2 % × 7 810,83 = 251,33 + 156,22 = 407,55
        //   NI pat. = 15 % × 11 583,33 = 1 737,50
        //   IR : assiette 11 592,45 → annuel 139 109,40 →
        //     7 540 + 29 948 + 13 969,40 × 45 % = 43 774,23 → mensuel 3 647,85
        //   Net = 12 000 − 407,55 − 3 647,85 = 7 944,60
        $charges = $this->rules()->calculateSocialCharges(12000.0);
        $this->assertSame(407.55, $charges['employee']);
        $this->assertSame(1737.50, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(12000.0 - $charges['employee']);
        $this->assertSame(3647.85, $tax);
        $this->assertSame(7944.60, round(12000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_below_primary_threshold_1000(): void
    {
        // Brut £1 000 < PT mensuel : NI salarié 0 · NI patron 15 % × 583,33 = 87,50
        // IR : annuel 12 000 ≤ 12 570 → 0 · Net = 1 000
        $charges = $this->rules()->calculateSocialCharges(1000.0);
        $this->assertSame(0.0, $charges['employee']);
        $this->assertSame(87.50, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(1000.0 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(1000.0, round(1000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_at_primary_threshold_1047_50(): void
    {
        // Brut = PT mensuel exact : NI salarié 0 (bande nulle) · NI patron 15 % × 630,83 = 94,6245 → 94,62
        // (arrondi au centime : 630,83 × 15 % = 94,6245 → 94,62, pas 94,63)
        // IR : annuel 12 570 → tranche 0 % → 0 · Net = 1 047,50
        $charges = $this->rules()->calculateSocialCharges(1047.50);
        $this->assertSame(0.0, $charges['employee']);
        $this->assertSame(94.62, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(1047.50 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(1047.50, round(1047.50 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_at_upper_earnings_limit_4189_17(): void
    {
        // Brut = UEL mensuel exact : NI sal. = 8 % × 3 141,67 = 251,33 (2 % non atteint)
        // NI pat. = 15 % × 3 772,50 = 565,88
        // IR : assiette 3 937,84 → annuel 47 254,08 → 34 684,08 × 20 % = 6 936,82 → mensuel 578,07
        // Net = 4 189,17 − 251,33 − 578,07 = 3 359,77
        $charges = $this->rules()->calculateSocialCharges(4189.17);
        $this->assertSame(251.33, $charges['employee']);
        $this->assertSame(565.88, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(4189.17 - $charges['employee']);
        $this->assertSame(578.07, $tax);
        $this->assertSame(3359.77, round(4189.17 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_higher_rate_10000(): void
    {
        // Brut £10 000 (tranche 40 %) :
        //   NI sal. = 251,33 + 2 % × 5 810,83 = 116,22 → 367,55 · NI pat. = 1 437,50
        //   IR : assiette 9 632,45 → annuel 115 589,40 → 7 540 + 26 127,76 = 33 667,76 → 2 805,65
        //   Net = 9 632,45 − 2 805,65 = 6 826,80
        $charges = $this->rules()->calculateSocialCharges(10000.0);
        $this->assertSame(367.55, $charges['employee']);
        $this->assertSame(1437.50, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(10000.0 - $charges['employee']);
        $this->assertSame(2805.65, $tax);
        $this->assertSame(6826.80, round(10000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_below_secondary_threshold_400(): void
    {
        // Brut £400 < ST mensuel : aucune cotisation NI (salarié ET patron)
        // IR : annuel 4 800 → 0 · Net = 400
        $charges = $this->rules()->calculateSocialCharges(400.0);
        $this->assertSame(0.0, $charges['employee']);
        $this->assertSame(0.0, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(400.0 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(400.0, round(400.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_at_secondary_threshold_416_67(): void
    {
        // Brut = ST mensuel exact : NI patron = 15 % × 0 = 0 · NI salarié 0
        // IR : annuel 5 000 → 0 · Net = 416,67
        $charges = $this->rules()->calculateSocialCharges(416.67);
        $this->assertSame(0.0, $charges['employee']);
        $this->assertSame(0.0, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(416.67 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(416.67, round(416.67 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_cadre_7000(): void
    {
        // Brut £7 000 :
        //   NI sal. = 251,33 + 2 % × 2 810,83 = 56,22 → 307,55 · NI pat. = 987,50
        //   IR : assiette 6 692,45 → annuel 80 309,40 → 7 540 + 12 015,76 = 19 555,76 → 1 629,65
        //   Net = 6 692,45 − 1 629,65 = 5 062,80
        $charges = $this->rules()->calculateSocialCharges(7000.0);
        $this->assertSame(307.55, $charges['employee']);
        $this->assertSame(987.50, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(7000.0 - $charges['employee']);
        $this->assertSame(1629.65, $tax);
        $this->assertSame(5062.80, round(7000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_gb_rules_metadata(): void
    {
        $rules = $this->rules();
        $this->assertSame('GB', $rules->countryCode());
        $this->assertSame('GBP', $rules->currency());
        $this->assertSame('pilot', $rules->confidenceLevel());
        $this->assertSame('en', $rules->language());
        $this->assertSame('Europe/London', $rules->timezone());
        $this->assertSame([6, 7], $rules->weeklyRestDays());
        $this->assertSame(['monthly'], $rules->supportedPayCycles());
        $this->assertSame(48.0, $rules->overtimeThresholdWeeklyHours());
        $this->assertSame([], $rules->overtimeRateTiers());
        // ERA 1996 s.86 : 1 semaine/année plafonnée 12, 0 avant 1 mois.
        $this->assertSame(0.0, $rules->noticePeriodDays(0.05));
        $this->assertSame(7.0, $rules->noticePeriodDays(0.5));
        $this->assertSame(28.0, $rules->noticePeriodDays(4.0));
        $this->assertSame(84.0, $rules->noticePeriodDays(12.0));
        $this->assertSame(0.2309, $rules->severanceMonthsPerYear(5.0));
        $this->assertNotEmpty($rules->complianceWarning());
        $this->assertSame('docs/payroll/GB_COMPLIANCE.md', $rules->complianceSource());
        $this->assertNull($rules->verificationDate());
        $this->assertCount(4, $rules->legalReferenceTaxSlabs());
        $this->assertCount(3, $rules->socialContributions());
        $this->assertSame(0.0, $rules->calculateBracketTax(5000.0));
        $this->assertFalse($rules->thirteenthMonthMandatory());
    }
}
