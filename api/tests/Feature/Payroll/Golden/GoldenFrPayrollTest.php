<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests France (FR) — issues #2119/#5254, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/FR_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Audit 2026 (#5254) : barème IR 2026 (LF 2026, +0,9 % — 0–11 600 € 0 %,
 * 11 601–29 579 € 11 %, 29 580–84 577 € 30 %, 84 578–181 917 € 41 %,
 * > 181 917 € 45 %) et SMIC 1 867,02 €/mois (1er juin 2026) — l'ancien
 * barème (2025) et SMIC (2024) étaient obsolètes.
 *
 * Règles (pilot) : SS salariale 7,5 % / patronale 30 % · CSG 9,2 % + CRDS 0,5 %
 * sur base 98,25 % du brut · IR mensuel = progressif ANNUEL / 12.
 */
class GoldenFrPayrollTest extends TestCase
{
    private function rules(): FrancePayrollRules
    {
        return new FrancePayrollRules;
    }

    public function test_golden_fr_smic_2026(): void
    {
        // Calcul manuel, brut = SMIC 1 867,02 € (1er juin 2026) :
        //   SS = 1 867,02 × 7,5 % = 140,03 · CSG = 1 834,34715 × 9,2 % = 168,76
        //   · CRDS = 1 834,34715 × 0,5 % = 9,17 → salarié 317,96
        //   IR : assiette 1 549,06 → annuel 18 588,72 :
        //     (18 588,72 − 11 600) × 11 % = 768,7592 → mensuel 64,06
        //   Net = 1 867,02 − 317,96 − 64,06 = 1 485,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(1867.02);
        $this->assertSame(317.96, $charges['employee']);
        $this->assertSame(560.11, $charges['employer']);

        $tax = $rules->calculateIncomeTax(1867.02 - $charges['employee']);
        $this->assertSame(64.06, $tax);
        $this->assertSame(1485.00, round(1867.02 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_cadre_moyen_4000(): void
    {
        // Calcul manuel, brut 4 000 € :
        //   SS = 300,00 · CSG = 3 930 × 9,2 % = 361,56 · CRDS = 19,65 → salarié 681,21
        //   IR : assiette 3 318,79 → annuel 39 825,48 :
        //     17 979 × 11 % + 10 246,48 × 30 % = 5 051,634 → mensuel 420,97
        //   Net = 4 000 − 681,21 − 420,97 = 2 897,82
        $charges = $this->rules()->calculateSocialCharges(4000.0);
        $this->assertSame(681.21, $charges['employee']);
        $this->assertSame(1200.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(4000.0 - $charges['employee']);
        $this->assertSame(420.97, $tax);
        $this->assertSame(2897.82, round(4000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_haut_salaire_15000(): void
    {
        // Calcul manuel, brut 15 000 € :
        //   SS = 1 125,00 · CSG = 14 737,50 × 9,2 % = 1 355,85 · CRDS = 73,69
        //   → salarié 2 554,54
        //   IR : assiette 12 445,46 → annuel 149 345,52 :
        //     17 979 × 11 % + 54 998 × 30 % + 64 768,52 × 41 % = 45 032,1832
        //     → mensuel 3 752,68
        //   Net = 15 000 − 2 554,54 − 3 752,68 = 8 692,78
        $charges = $this->rules()->calculateSocialCharges(15000.0);
        $this->assertSame(2554.54, $charges['employee']);
        $this->assertSame(4500.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(15000.0 - $charges['employee']);
        $this->assertSame(3752.68, $tax);
        $this->assertSame(8692.78, round(15000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_profil_3000(): void
    {
        // Brut 3 000 € : salarié 510,91 (SS 225,00 + CSG 271,17 + CRDS 14,74)
        //   IR : assiette 2 489,09 → annuel 29 869,08 :
        //     17 979 × 11 % + 290,08 × 30 % = 2 064,714 → mensuel 172,06
        //   Net = 3 000 − 510,91 − 172,06 = 2 317,03
        $charges = $this->rules()->calculateSocialCharges(3000.0);
        $this->assertSame(510.91, $charges['employee']);
        $this->assertSame(900.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(3000.0 - $charges['employee']);
        $this->assertSame(172.06, $tax);
        $this->assertSame(2317.03, round(3000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_profil_10000(): void
    {
        // Brut 10 000 € : salarié 1 703,03 (SS 750,00 + CSG 903,90 + CRDS 49,13)
        //   IR : assiette 8 296,97 → annuel 99 563,64 :
        //     17 979 × 11 % + 54 998 × 30 % + 14 986,64 × 41 % = 24 621,55
        //     → mensuel 2 051,80
        //   Net = 10 000 − 1 703,03 − 2 051,80 = 6 245,17
        $charges = $this->rules()->calculateSocialCharges(10000.0);
        $this->assertSame(1703.03, $charges['employee']);
        $this->assertSame(3000.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(10000.0 - $charges['employee']);
        $this->assertSame(2051.80, $tax);
        $this->assertSame(6245.17, round(10000.0 - $charges['employee'] - $tax, 2));
    }

    /**
     * Frontières exactes du barème IR 2026 (annuel × 1 €, converti en mensuel).
     * Valeurs de taxe ANNELLE calculées à la main (FR_COMPLIANCE.md §1) :
     *   11 600 → 0,00 · 11 601 → 0,11 · 29 579 → 1 977,69 · 29 580 → 1 977,99
     *   84 577 → 18 477,09 · 84 578 → 18 477,50 · 181 917 → 58 386,49
     *   181 918 → 58 386,94 · 200 000 → 66 523,84 (tranche 45 %).
     *
     * @return iterable<string, array{0: float, 1: float}>
     */
    public static function frTaxEdgesProvider(): iterable
    {
        yield '11 600 (fin 0 %)' => [11600.0, 0.0];
        yield '11 601 (début 11 %)' => [11601.0, 0.11];
        yield '29 579 (fin 11 %)' => [29579.0, 1977.69];
        yield '29 580 (début 30 %)' => [29580.0, 1977.99];
        yield '84 577 (fin 30 %)' => [84577.0, 18477.09];
        yield '84 578 (début 41 %)' => [84578.0, 18477.50];
        yield '181 917 (fin 41 %)' => [181917.0, 58386.49];
        yield '181 918 (début 45 %)' => [181918.0, 58386.94];
        yield '200 000 (tranche 45 %)' => [200000.0, 66523.84];
    }

    #[DataProvider('frTaxEdgesProvider')]
    public function test_golden_fr_ir_2026_bracket_edges(float $annual, float $expectedAnnualTax): void
    {
        // calculateIncomeTax reçoit un MENSUEL : on passe annual/12 et on
        // vérifie le mensuel = round(annuel / 12, 2).
        $monthlyTax = $this->rules()->calculateIncomeTax($annual / 12);
        $this->assertSame(round($expectedAnnualTax / 12, 2), $monthlyTax);
    }

    public function test_golden_fr_csg_crds_assiette_9825_percent(): void
    {
        // Preuve de l'assiette CSG/CRDS = 98,25 % du brut (constante légale) :
        //   brut 4 000 → base 3 930 → CSG 361,56 · CRDS 19,65.
        // La charge salariale totale (681,21) doit être la somme des lignes
        // arrondies (constitution §III, arrondi par ligne).
        $charges = $this->rules()->calculateSocialCharges(4000.0);
        $this->assertSame(round(4000.0 * 0.9825 * 0.092, 2), 361.56);
        $this->assertSame(round(4000.0 * 0.9825 * 0.005, 2), 19.65);
        $this->assertSame(681.21, $charges['employee']);
    }
}
