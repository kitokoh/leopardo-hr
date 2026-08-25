<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\FrancePayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests France (FR) — issues #2119/#5254/#5438, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN
 * (docs/payroll/FR_COMPLIANCE.md), pas reprise du code — une divergence =
 * régression de conformité.
 *
 * Modèle #5438 (pilot, structure URSSAF détaillée — PMSS 2026 = 4 005 €) :
 *   Maladie 0/13,00 % · Vieillesse plafonnée 6,90/8,55 % (cap PMSS) ·
 *   Vieillesse déplafonnée 0,40/1,90 % · Retraite complémentaire T1
 *   3,15/4,72 % (cap PMSS) · Prévoyance 1,50/1,50 % · Chômage 0/4,05 % ·
 *   FNGS 0/0,50 % · CSG 9,20 % + CRDS 0,50 % sur base 98,25 % du brut.
 *   IR mensuel = progressif ANNUEL / 12 (assiette = brut − cotisations
 *   salariales) · Fillon T = 0,3206 (≥ 20 salariés) · PAS taux personnalisé.
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
        //   VIE_PLF 128,82 (6,9 % · < PMSS) + VIE_DPL 7,47 (0,4 %) +
        //   RET_T1 58,81 (3,15 %) + PREV 28,01 (1,5 %) +
        //   CSG 1 834,34715 × 9,2 % = 168,76 + CRDS 1 834,34715 × 0,5 % = 9,17
        //   → salarié 401,04
        //   Employeur : MAL 242,71 + VIE_PLF 159,63 + VIE_DPL 35,47 +
        //   RET_T1 88,12 + PREV 28,01 + CHO 75,61 + FNGS 9,34 → 638,89
        //   IR : assiette 1 465,98 → annuel 17 591,76 :
        //     (17 591,76 − 11 600) × 11 % = 659,0936 → mensuel 54,92
        //   Net = 1 867,02 − 401,04 − 54,92 = 1 411,06
        //   Net social = 1 867,02 − 401,04 = 1 465,98
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(1867.02);
        $this->assertSame(401.04, $charges['employee']);
        $this->assertSame(638.89, $charges['employer']);

        $tax = $rules->calculateIncomeTax(1867.02 - $charges['employee']);
        $this->assertSame(54.92, $tax);
        $this->assertSame(1411.06, round(1867.02 - $charges['employee'] - $tax, 2));
        $this->assertSame(1465.98, $rules->netSocial(1867.02, $charges['employee']));
    }

    public function test_golden_fr_cadre_moyen_4000(): void
    {
        // Calcul manuel, brut 4 000 € (< PMSS) :
        //   VIE_PLF 276,00 + VIE_DPL 16,00 + RET_T1 126,00 + PREV 60,00 +
        //   CSG 3 930 × 9,2 % = 361,56 + CRDS 3 930 × 0,5 % = 19,65
        //   → salarié 859,21
        //   Employeur : MAL 520,00 + VIE_PLF 342,00 + VIE_DPL 76,00 +
        //   RET_T1 188,80 + PREV 60,00 + CHO 162,00 + FNGS 20,00 → 1 368,80
        //   IR : assiette 3 140,79 → annuel 37 689,48 :
        //     17 979 × 11 % + 8 109,48 × 30 % = 4 410,534 → mensuel 367,57
        //   Net = 4 000 − 859,21 − 367,57 = 2 773,22
        $charges = $this->rules()->calculateSocialCharges(4000.0);
        $this->assertSame(859.21, $charges['employee']);
        $this->assertSame(1368.80, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(4000.0 - $charges['employee']);
        $this->assertSame(367.57, $tax);
        $this->assertSame(2773.22, round(4000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_profil_3000(): void
    {
        // Brut 3 000 € : salarié 644,41 (VIE_PLF 207,00 + VIE_DPL 12,00 +
        //   RET_T1 94,50 + PREV 45,00 + CSG 271,17 + CRDS 14,74)
        //   Employeur 1 026,60 (MAL 390,00 + VIE_PLF 256,50 + VIE_DPL 57,00 +
        //   RET_T1 141,60 + PREV 45,00 + CHO 121,50 + FNGS 15,00)
        //   IR : assiette 2 355,59 → annuel 28 267,08 :
        //     17 979 × 11 % + 0 (sous 29 580) → 1 833,3788 → mensuel 152,78
        //   Net = 3 000 − 644,41 − 152,78 = 2 202,81 · Coût = 4 026,60
        $charges = $this->rules()->calculateSocialCharges(3000.0);
        $this->assertSame(644.41, $charges['employee']);
        $this->assertSame(1026.60, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(3000.0 - $charges['employee']);
        $this->assertSame(152.78, $tax);
        $this->assertSame(2202.81, round(3000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_temps_partiel_1200_ir_zero(): void
    {
        // Temps partiel 1 200 € : salarié 257,76 (VIE_PLF 82,80 + VIE_DPL 4,80
        //   + RET_T1 37,80 + PREV 18,00 + CSG 108,47 + CRDS 5,89)
        //   Assiette 942,24 → annuel 11 306,88 < 11 600 → IR 0.
        //   Net = 1 200 − 257,76 = 942,24.
        $charges = $this->rules()->calculateSocialCharges(1200.0);
        $this->assertSame(257.76, $charges['employee']);
        $this->assertSame(410.64, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(1200.0 - $charges['employee']);
        $this->assertSame(0.0, $tax);
        $this->assertSame(942.24, round(1200.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_fr_fillon_reduction(): void
    {
        // Réduction générale (ex-Fillon) — SMIC 1 867,02 € :
        //   coefficient = (0,3206/0,6) × (1,6 × 1 − 1) = 0,3206 (max)
        //   → réduction = 0,3206 × 1 867,02 = 598,57 €/mois
        //   Au SMIC × 1,6 (2 987,23 €) et au-delà : 0.
        $rules = $this->rules();
        $this->assertSame(598.57, $rules->fillonReduction(1867.02));
        $this->assertSame(0.0, $rules->fillonReduction(4000.0));
        $this->assertSame(0.0, $rules->fillonReduction(2987.24));
    }

    public function test_golden_fr_pas_personalise(): void
    {
        // PAS taux personnalisé 8 % — assiette nette imposable SMIC 1 465,98 :
        //   1 465,98 × 8 % = 117,28.
        $this->assertSame(117.28, $this->rules()->withholdingTax(1465.98, 8.0));
        $this->assertSame(0.0, $this->rules()->withholdingTax(0.0, 8.0));
    }

    public function test_golden_fr_pmss_2026(): void
    {
        // PMSS 2026 = 4 005 €/mois (PASS 48 060 €, +2 %).
        $this->assertSame(4005.0, $this->rules()->pmss());

        // Plafonnement : brut 5 000 € → vieillesse plafonnée sur 4 005 €.
        $charges = $this->rules()->calculateSocialCharges(5000.0);
        // 6,9 % × 4 005 = 276,35 (au lieu de 345,00 sans plafond).
        $this->assertGreaterThan(0.0, $charges['employee']);
    }

    public function test_golden_fr_haut_salaire_15000(): void
    {
        // Brut 15 000 € (> PMSS) : vieillesse plafonnée et retraite T1
        // plafonnées à 4 005 € ; vieillesse déplafonnée/CSG/CRDS sur le brut.
        //   VIE_PLF 4 005 × 6,9 % = 276,35 · VIE_DPL 60,00 · RET_T1
        //   4 005 × 3,15 % = 126,16 · PREV 225,00 · CSG 14 737,50 × 9,2 % =
        //   1 355,85 · CRDS 73,69 → salarié 2 117,05
        $charges = $this->rules()->calculateSocialCharges(15000.0);
        $this->assertSame(2117.05, $charges['employee']);
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
        $charges = $this->rules()->calculateSocialCharges(4000.0);
        $this->assertSame(round(4000.0 * 0.9825 * 0.092, 2), 361.56);
        $this->assertSame(round(4000.0 * 0.9825 * 0.005, 2), 19.65);
        $this->assertSame(859.21, $charges['employee']);
    }
}
