<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SnPayrollFixtures;
use Tests\TestCase;

/**
 * Golden tests Sénégal (SN) — cas limites complémentaires (issue #5250).
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/SN_COMPLIANCE.md,
 * constitution §III), pas reprise du code. Les règles sont validées
 * `production` (expert-comptable 2026-08-18, #1912).
 *
 * Cas ajoutés (complément des 20 tests existants) :
 *   1. Frontières TRIMF (CGI art. 185) : 10 bornes exactes ± 1 FCFA.
 *   2. Plafond CSS (famille 7 % + AT 1 %, 63 000 XOF/mois).
 *   3. Plafond d'abattement frais pro effectivement atteint (CGI art. 168).
 *   4. IPRES T2 par catégorie d'employé (ouvrier vs cadre — #1912).
 *   5. Profil cadre complet : charges + IR + TRIMF combinés.
 */
class GoldenSnEdgeCasesTest extends TestCase
{
    private function rules(): SenegalPayrollRules
    {
        return new SenegalPayrollRules;
    }

    /**
     * Frontières TRIMF — la taxe forfaitaire mensuelle bascule à chaque borne.
     *
     * @return iterable<string, array{0: float, 1: float}>
     */
    public static function trimfEdgeProvider(): iterable
    {
        yield 'borne 75 000' => [75000.0, 900.0];
        yield '75 001' => [75001.0, 1800.0];
        yield 'borne 200 000' => [200000.0, 1800.0];
        yield '200 001' => [200001.0, 3600.0];
        yield 'borne 600 000' => [600000.0, 3600.0];
        yield '600 001' => [600001.0, 7200.0];
        yield 'borne 1 000 000' => [1000000.0, 7200.0];
        yield '1 000 001' => [1000001.0, 12000.0];
        yield 'borne 1 500 000' => [1500000.0, 12000.0];
        yield '1 500 001' => [1500001.0, 18000.0];
    }

    #[DataProvider('trimfEdgeProvider')]
    public function test_golden_sn_trimf_bracket_edges(float $gross, float $expected): void
    {
        // CGI Sénégal art. 185 — barème 2026 (900/1 800/3 600/7 200/12 000/18 000).
        $this->assertSame($expected, $this->rules()->calculateBracketTax($gross));
    }

    public function test_golden_sn_css_cap_boundary(): void
    {
        // CSS famille 7 % + AT 1 % plafonnées à 63 000 XOF/mois :
        //   à 63 000 exactement : famille = 63 000 × 7 % = 4 410 · AT = 630
        //   à 100 000 : base plafonnée 63 000 → mêmes 4 410 + 630 (l'écart
        //   n'apparaît que sur l'IPRES T1 et la CFCE).
        //   g=63 000 : salariale T1 = 3 528 · patronal = 5 292 + 4 410 + 630
        //   + 1 890 (CFCE) = 12 222
        //   g=100 000 : salariale 5 600 · patronal = 8 400 + 4 410 + 630
        //   + 3 000 = 16 440
        $rules = $this->rules();

        $chargesAtCap = $rules->calculateSocialCharges(63000.0);
        $this->assertSame(3528.0, $chargesAtCap['employee']);
        $this->assertSame(12222.0, $chargesAtCap['employer']);

        $chargesAbove = $rules->calculateSocialCharges(100000.0);
        $this->assertSame(5600.0, $chargesAbove['employee']);
        $this->assertSame(16440.0, $chargesAbove['employer']);

        // Preuve du plafonnement : part CSS (famille 4 410 + AT 630 = 5 040)
        // identique dans les deux cas — seul le T1 et la CFCE progressent.
        $this->assertSame(5040.0, $chargesAtCap['employer'] - 5292.0 - 1890.0);
        $this->assertSame(5040.0, $chargesAbove['employer'] - 8400.0 - 3000.0);
    }

    public function test_golden_sn_abatement_cap_binds_at_300000(): void
    {
        // CGI art. 168 : abattement 30 % du BRUT plafonné à 75 000 XOF/mois.
        // Brut 300 000 : 30 % = 90 000 → plafonné 75 000 (le plafond s'applique
        // réellement ; à 250 000, 30 % = 75 000 exactement — borne).
        //   IPRES salariale (T1, < 432 000) = 16 800 → assiette 283 200
        //   abattement plafonné 75 000 → 208 200 → annuel 2 498 400
        //   IR : 630 000 × 0 % + 870 000 × 20 % (174 000)
        //        + 998 400 × 30 % (299 520) = 473 520/an → 39 460,00/mois
        //   TRIMF (300 000 ≤ 600 000) = 3 600 → total retenu 43 060,00
        $rules = $this->rules();
        $base = 300000.0 - $rules->calculateSocialCharges(300000.0)['employee'];

        $this->assertSame(39460.0, $rules->calculateIncomeTax($base, 12, 300000.0));
        $this->assertSame(3600.0, $rules->calculateBracketTax(300000.0));
        $this->assertSame(43060.0, round(39460.0 + 3600.0, 2));
    }

    public function test_golden_sn_ipres_t2_is_category_dependent(): void
    {
        // #1912 : le régime cadres T2 ne s'applique qu'aux employés de
        // catégorie 'cadre' (employees.ipres_category) — pas aux ouvriers.
        // Brut 600 000 (> plafond T1 432 000) :
        //   ouvrier : T1 seul → salariale 24 192 (24 192 + 0 T2) · patronal
        //     36 288 + 0 + 4 410 + 630 + 18 000 = 59 328
        //   cadre  : T1 24 192 + T2 (168 000 × 2,4 %) 4 032 = 28 224 · patronal
        //     36 288 + 6 048 + 4 410 + 630 + 18 000 = 65 376
        $rules = $this->rules();

        $ouvrier = $rules->calculateSocialChargesWithCategory(600000.0, 'ouvrier');
        $this->assertSame(24192.0, $ouvrier['employee']);
        $this->assertSame(59328.0, $ouvrier['employer']);

        $cadre = $rules->calculateSocialChargesWithCategory(600000.0, 'cadre');
        $this->assertSame(28224.0, $cadre['employee']);
        $this->assertSame(65376.0, $cadre['employer']);
    }

    public function test_golden_sn_full_path_cadre_600000(): void
    {
        // Profil cadre complet (brut 600 000) — charges + IR + TRIMF :
        //   salariale 28 224 (T1 24 192 + T2 4 032) · patronale 65 376
        //   assiette IR = 600 000 − 28 224 = 571 776 ; abattement plafonné
        //   75 000 → 496 776 → annuel 5 961 312
        //   IR : 870 000 × 20 % (174 000) + 2 500 000 × 30 % (750 000)
        //        + 1 961 312 × 35 % (686 459,20) = 1 610 459,20/an
        //        → 134 204,93/mois
        //   TRIMF = 3 600 → total retenu 137 804,93
        $rules = $this->rules();

        $charges = $rules->calculateSocialChargesWithCategory(600000.0, 'cadre');
        $this->assertSame(28224.0, $charges['employee']);
        $this->assertSame(65376.0, $charges['employer']);
        $this->assertSame(SnPayrollFixtures::socialCharges(600000.0), $charges);

        $base = 600000.0 - $charges['employee'];
        $ir = $rules->calculateIncomeTax($base, 12, 600000.0);
        $this->assertSame(134204.93, $ir);
        $this->assertSame(137804.93, round($ir + $rules->calculateBracketTax(600000.0), 2));
    }

    public function test_golden_sn_legacy_mode_matches_fixture(): void
    {
        // Mode historique (sans catégorie) : T2 déclenché par seuil de brut —
        // les valeurs doivent rester alignées sur SnPayrollFixtures (source
        // de référence #2541).
        $rules = $this->rules();

        foreach ([0.0, 64305.43, 100000.0, 250000.0, 432000.0, 600000.0, 1000000.0, 3000000.0] as $gross) {
            $this->assertSame(
                SnPayrollFixtures::socialCharges($gross),
                $rules->calculateSocialCharges($gross),
                "Divergence moteur vs fixture à {$gross} XOF",
            );
        }
    }
}
