<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TurkeyPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests Turquie (TR) — issues #2119/#5253, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/TR_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Audit 2026 (#5253) : asgari ücret 33 030,00 TRY brut et barème gelir vergisi
 * salariés 2026 (0–190 000 15 %, 190 001–400 000 20 %, 400 001–1 500 000 27 %,
 * 1 500 001–5 300 000 35 %, > 5 300 000 40 %) — l'ancien barème (2024) et
 * SMIG (2024) étaient obsolètes.
 *
 * Règles (pilot) : SGK 14 % + chômage 1 % salarial (15 % total), SGK 20,5 % +
 * chômage 2 % patronal (22,5 % total), non plafonnés (tavan réel 7,5 × asgari
 * ücret = 247 725 TRY — gap E3 documenté) · IR mensuel = progressif ANNUEL / 12.
 */
class GoldenTrPayrollTest extends TestCase
{
    private function rules(): TurkeyPayrollRules
    {
        return new TurkeyPayrollRules;
    }

    public function test_golden_tr_asgari_ucret_2026(): void
    {
        // Calcul manuel, brut = asgari ücret 33 030 TRY :
        //   SGK+chômage salarial = 33 030 × 15 % = 4 954,50
        //   IR : assiette 28 075,50 → annuel 336 906 :
        //     190 000 × 15 % + 146 906 × 20 % = 57 881,20 → mensuel 4 823,43
        //   Net moteur = 33 030 − 4 954,50 − 4 823,43 = 23 252,07
        //   ⚠️ net officiel 28 075,50 (istisna asgari ücret non modélisée — gap E1)
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(33030.0);
        $this->assertSame(4954.50, $charges['employee']);
        $this->assertSame(7431.75, $charges['employer']);

        $tax = $rules->calculateIncomeTax(33030.0 - $charges['employee']);
        $this->assertSame(4823.43, $tax);
        $this->assertSame(23252.07, round(33030.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tr_cadre_moyen_60000(): void
    {
        // Calcul manuel, brut 60 000 TRY :
        //   salarial = 9 000,00 · IR : assiette 51 000 → annuel 612 000 :
        //     190 000 × 15 % + 210 000 × 20 % + 212 000 × 27 %
        //     = 127 740 → mensuel 10 645,00
        //   Net = 60 000 − 9 000 − 10 645,00 = 40 355,00
        $charges = $this->rules()->calculateSocialCharges(60000.0);
        $this->assertSame(9000.00, $charges['employee']);
        $this->assertSame(13500.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(60000.0 - $charges['employee']);
        $this->assertSame(10645.00, $tax);
        $this->assertSame(40355.00, round(60000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tr_haut_salaire_240000(): void
    {
        // Calcul manuel, brut 240 000 TRY (sous le tavan SGK 247 725 — gap E3) :
        //   salarial = 36 000,00 · IR : assiette 204 000 → annuel 2 448 000 :
        //     190 000 × 15 % + 210 000 × 20 % + 1 100 000 × 27 % + 948 000 × 35 %
        //     = 699 300 → mensuel 58 275,00
        //   Net = 240 000 − 36 000 − 58 275,00 = 145 725,00
        $charges = $this->rules()->calculateSocialCharges(240000.0);
        $this->assertSame(36000.00, $charges['employee']);
        $this->assertSame(54000.00, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(240000.0 - $charges['employee']);
        $this->assertSame(58275.00, $tax);
        $this->assertSame(145725.00, round(240000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tr_charges_employeur_225_percent(): void
    {
        // Preuve : employeur = SGK 20,5 % + chômage 2 % = 22,5 % du brut
        // (33 030 → 7 431,75 ; 60 000 → 13 500,00).
        $this->assertSame(7431.75, $this->rules()->calculateSocialCharges(33030.0)['employer']);
        $this->assertSame(13500.00, $this->rules()->calculateSocialCharges(60000.0)['employer']);
    }

    public function test_golden_tr_arrondi_ligne_sgk(): void
    {
        // Arrondi par ligne (constitution §III) : 33 030 × 15 % = 4 954,50 exact
        // (pas d'arrondi cumulé SGK 14 % = 4 624,20 + chômage 1 % = 330,30).
        $this->assertSame(4624.20, round(33030.0 * 0.14, 2));
        $this->assertSame(330.30, round(33030.0 * 0.01, 2));
        $this->assertSame(4954.50, $this->rules()->calculateSocialCharges(33030.0)['employee']);
    }

    /**
     * Frontières exactes du barème gelir vergisi 2026 (annuel × 1 TRY,
     * converti en mensuel). Taxes ANNELLES calculées à la main :
     *   190 000 → 28 500,00 · 190 001 → 28 500,20 · 400 000 → 70 500,00
     *   400 001 → 70 500,27 · 1 500 000 → 367 500,00 · 1 500 001 → 367 500,35
     *   5 300 000 → 1 697 500,00 · 5 300 001 → 1 697 500,40 · 7 000 000 → 2 377 500,00.
     *
     * @return iterable<string, array{0: float, 1: float}>
     */
    public static function trTaxEdgesProvider(): iterable
    {
        yield '190 000 (fin 15 %)' => [190000.0, 28500.0];
        yield '190 001 (début 20 %)' => [190001.0, 28500.2];
        yield '400 000 (fin 20 %)' => [400000.0, 70500.0];
        yield '400 001 (début 27 %)' => [400001.0, 70500.27];
        yield '1 500 000 (fin 27 %)' => [1500000.0, 367500.0];
        yield '1 500 001 (début 35 %)' => [1500001.0, 367500.35];
        yield '5 300 000 (fin 35 %)' => [5300000.0, 1697500.0];
        yield '5 300 001 (début 40 %)' => [5300001.0, 1697500.4];
        yield '7 000 000 (tranche 40 %)' => [7000000.0, 2377500.0];
    }

    #[DataProvider('trTaxEdgesProvider')]
    public function test_golden_tr_ir_2026_bracket_edges(float $annual, float $expectedAnnualTax): void
    {
        $monthlyTax = $this->rules()->calculateIncomeTax($annual / 12);
        $this->assertSame(round($expectedAnnualTax / 12, 2), $monthlyTax);
    }
}
