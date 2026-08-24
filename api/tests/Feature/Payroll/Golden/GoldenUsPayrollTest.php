<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\UnitedStatesPayrollRules;
use Tests\TestCase;

/**
 * Golden tests États-Unis (US) — pack EN #5255, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/US_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot, fédéral 2026, single) :
 *  - FICA : Social Security 6,2 % chacun sur le wage base annuel $184 500
 *    ($15 375/mois) ; Medicare 1,45 % chacun sans plafond ; Additional
 *    Medicare 0,9 % (salarié seul) au-delà de $200 000/an.
 *  - FUTA : 0,6 % effectif (employeur seul) sur les premiers $7 000/an
 *    ($583,33/mois).
 *  - Federal income tax : standard deduction $16 100 (single) puis barème
 *    ANNUEL 10 % ≤ 12 400 · 12 % ≤ 50 400 · 22 % ≤ 105 700 · 24 % ≤ 201 775 ·
 *    32 % ≤ 256 225 · 35 % ≤ 640 600 · 37 % au-delà, le tout / 12.
 */
class GoldenUsPayrollTest extends TestCase
{
    private function rules(): UnitedStatesPayrollRules
    {
        return new UnitedStatesPayrollRules();
    }

    public function test_golden_us_federal_minimum_wage_1256_64(): void
    {
        // Brut = minimum fédéral $1 256,64 :
        //   SS = 77,91 · Medicare = 18,22 → salarié 96,13
        //   patron = 77,91 + 18,22 + FUTA 3,50 = 99,63
        //   IR : assiette 1 160,51 → annuel 13 926,12 < 16 100 (standard deduction) → 0
        //   Net = 1 256,64 − 96,13 = 1 160,51
        $charges = $this->rules()->calculateSocialCharges(1256.64);
        $this->assertSame(96.13, $charges['employee']);
        $this->assertSame(99.63, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(1256.64 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(1160.51, round(1256.64 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_cadre_moyen_4000(): void
    {
        // Brut $4 000 :
        //   SS = 248,00 · Medicare = 58,00 → salarié 306,00
        //   patron = 248,00 + 58,00 + FUTA 3,50 = 309,50
        //   IR : assiette 3 694 → annuel 44 328 − 16 100 = 28 228 →
        //     12 400 × 10 % + 15 828 × 12 % = 3 139,36 → mensuel 261,61
        //   Net = 3 694 − 261,61 = 3 432,39
        $charges = $this->rules()->calculateSocialCharges(4000.0);
        $this->assertSame(306.00, $charges['employee']);
        $this->assertSame(309.50, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(4000.0 - $charges['employee']);
        $this->assertSame(261.61, $tax);
        $this->assertSame(3432.39, round(4000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_cadre_10000(): void
    {
        // Brut $10 000 :
        //   SS = 620,00 · Medicare = 145,00 → salarié 765,00 · patron = 768,50
        //   IR : assiette 9 235 → annuel 110 820 − 16 100 = 94 720 →
        //     1 240 + 4 560 + 44 320 × 22 % = 15 550,40 → mensuel 1 295,87
        //   Net = 9 235 − 1 295,87 = 7 939,13
        $charges = $this->rules()->calculateSocialCharges(10000.0);
        $this->assertSame(765.00, $charges['employee']);
        $this->assertSame(768.50, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(10000.0 - $charges['employee']);
        $this->assertSame(1295.87, $tax);
        $this->assertSame(7939.13, round(10000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_haut_salaire_20000(): void
    {
        // Brut $20 000 (SS plafonnée + Additional Medicare déclenché) :
        //   SS = 6,2 % × 15 375 = 953,25 · Medicare = 290,00 ·
        //   Addl = (240 000 − 200 000)/12 × 0,9 % = 30,00 → salarié 1 273,25
        //   patron = 953,25 + 290,00 + 3,50 = 1 246,75
        //   IR : assiette 18 726,75 → annuel 224 721 − 16 100 = 208 621 →
        //     1 240 + 4 560 + 12 166 + 23 058 + 6 846 × 32 % = 43 214,72 → 3 601,23
        //   Net = 18 726,75 − 3 601,23 = 15 125,52
        $charges = $this->rules()->calculateSocialCharges(20000.0);
        $this->assertSame(1273.25, $charges['employee']);
        $this->assertSame(1246.75, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(20000.0 - $charges['employee']);
        $this->assertSame(3601.23, $tax);
        $this->assertSame(15125.52, round(20000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_at_ss_wage_base_15375(): void
    {
        // Brut = base SS mensuelle exacte $15 375 :
        //   SS = 953,25 · Medicare = 222,94 → salarié 1 176,19 · patron = 1 179,69
        //   IR : assiette 14 198,81 → annuel 170 385,72 − 16 100 = 154 285,72 →
        //     1 240 + 4 560 + 12 166 + 48 585,72 × 24 % = 29 626,57 → 2 468,88
        //   Net = 14 198,81 − 2 468,88 = 11 729,93
        $charges = $this->rules()->calculateSocialCharges(15375.0);
        $this->assertSame(1176.19, $charges['employee']);
        $this->assertSame(1179.69, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(15375.0 - $charges['employee']);
        $this->assertSame(2468.88, $tax);
        $this->assertSame(11729.93, round(15375.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_at_additional_medicare_threshold_16666_67(): void
    {
        // Brut = seuil Additional Medicare mensuel (annuel $200 000) :
        //   SS = 953,25 · Medicare = 241,67 · Addl = 0 (seuil non dépassé)
        //   → salarié 1 194,92 · patron = 1 198,42
        //   IR : assiette 15 471,75 → annuel 185 661 − 16 100 = 169 561 →
        //     1 240 + 4 560 + 12 166 + 63 861 × 24 % = 33 292,64 → 2 774,39
        //   Net = 15 471,75 − 2 774,39 = 12 697,36
        $charges = $this->rules()->calculateSocialCharges(16666.67);
        $this->assertSame(1194.92, $charges['employee']);
        $this->assertSame(1198.42, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(16666.67 - $charges['employee']);
        $this->assertSame(2774.39, $tax);
        $this->assertSame(12697.36, round(16666.67 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_tranche_32_25000(): void
    {
        // Brut $25 000 :
        //   SS = 953,25 · Medicare = 362,50 · Addl = (300 000 − 200 000)/12 × 0,9 % = 75,00
        //   → salarié 1 390,75 · patron = 953,25 + 362,50 + 3,50 = 1 319,25
        //   IR : assiette 23 609,25 → annuel 283 311 − 16 100 = 267 211 →
        //     1 240 + 4 560 + 12 166 + 23 058 + 54 450 × 32 % + 10 986 × 35 %
        //     = 62 293,10 → mensuel 5 191,09
        //   Net = 23 609,25 − 5 191,09 = 18 418,16
        $charges = $this->rules()->calculateSocialCharges(25000.0);
        $this->assertSame(1390.75, $charges['employee']);
        $this->assertSame(1319.25, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(25000.0 - $charges['employee']);
        $this->assertSame(5191.09, $tax);
        $this->assertSame(18418.16, round(25000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_tranche_35_50000(): void
    {
        // Brut $50 000 :
        //   SS = 953,25 · Medicare = 725,00 · Addl = (600 000 − 200 000)/12 × 0,9 % = 300,00
        //   → salarié 1 978,25 · patron = 953,25 + 725,00 + 3,50 = 1 681,75
        //   IR : assiette 48 021,75 → annuel 576 261 − 16 100 = 560 161 →
        //     1 240 + 4 560 + 12 166 + 23 058 + 17 424 + 303 936 × 35 % = 164 825,60
        //     → mensuel 13 735,47 · Net = 48 021,75 − 13 735,47 = 34 286,28
        $charges = $this->rules()->calculateSocialCharges(50000.0);
        $this->assertSame(1978.25, $charges['employee']);
        $this->assertSame(1681.75, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(50000.0 - $charges['employee']);
        $this->assertSame(13735.47, $tax);
        $this->assertSame(34286.28, round(50000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_at_futa_cap_583_33(): void
    {
        // Brut = plafond FUTA mensuel $583,33 :
        //   SS = 36,17 · Medicare = 8,46 → salarié 44,62 · patron = 36,17 + 8,46 + 3,50 = 48,12
        //   IR : annuel 6 464,52 < 16 100 → 0 · Net = 583,33 − 44,62 = 538,71
        $charges = $this->rules()->calculateSocialCharges(583.33);
        $this->assertSame(44.62, $charges['employee']);
        $this->assertSame(48.12, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(583.33 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(538.71, round(583.33 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_us_tranche_37_100000(): void
    {
        // Brut $100 000 (tranche 37 %) :
        //   SS = 953,25 · Medicare = 1 450,00 · Addl = (1 200 000 − 200 000)/12 × 0,9 % = 750,00
        //   → salarié 3 153,25 · patron = 953,25 + 1 450,00 + 3,50 = 2 406,75
        //   IR : assiette 96 846,75 → annuel 1 162 161 − 16 100 = 1 146 061 →
        //     1 240 + 4 560 + 12 166 + 23 058 + 17 424 + 134 531,25 + 505 461 × 37 %
        //     = 379 999,82 → mensuel 31 666,65 · Net = 96 846,75 − 31 666,65 = 65 180,10
        $charges = $this->rules()->calculateSocialCharges(100000.0);
        $this->assertSame(3153.25, $charges['employee']);
        $this->assertSame(2406.75, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(100000.0 - $charges['employee']);
        $this->assertSame(31666.65, $tax);
        $this->assertSame(65180.10, round(100000.0 - $charges['employee'] - $tax, 2));
    }
}
