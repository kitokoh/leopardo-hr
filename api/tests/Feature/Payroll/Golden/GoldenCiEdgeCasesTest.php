<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests Côte d'Ivoire (CI) — cas limites (issue #5251).
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/CI_COMPLIANCE.md,
 * constitution §III). Complément des 28 tests existants :
 *   1. Frontières EXACTES du barème ITS 2024 (CGI art. 119 bis — réforme
 *      ordonnance 2023-718/719) : 6 tranches mensuelles 0/16/21/24/28/32 %,
 *      chaque borne testée à ± 1 FCFA.
 *   2. Plafonds CNSS : retraite 1 647 315 XOF (borne exacte + au-delà) et
 *      famille 5,75 % + AT 2 % plafonnés 70 000 (borne exacte + au-delà).
 */
class GoldenCiEdgeCasesTest extends TestCase
{
    private function rules(): CedeaoPayrollRules
    {
        return new CedeaoPayrollRules('CI');
    }

    /**
     * Frontières ITS 2024 (mensuel progressif sur le BRUT — plus d'abattement).
     *
     * @return iterable<string, array{0: float, 1: float}>
     */
    public static function itsEdgeProvider(): iterable
    {
        yield 'borne 75 000 (fin tranche 0 %)' => [75000.0, 0.0];
        yield '75 001 (tranche 16 %)' => [75001.0, 0.16];
        yield 'borne 240 000 (fin 16 %)' => [240000.0, 26400.0];
        yield '240 001 (tranche 21 %)' => [240001.0, 26400.21];
        yield 'borne 800 000 (fin 21 %)' => [800000.0, 144000.0];
        yield '800 001 (tranche 24 %)' => [800001.0, 144000.24];
        yield 'borne 2 400 000 (fin 24 %)' => [2400000.0, 528000.0];
        yield '2 400 001 (tranche 28 %)' => [2400001.0, 528000.28];
        yield 'borne 8 000 000 (fin 28 %)' => [8000000.0, 2096000.0];
        yield '8 000 001 (tranche 32 %)' => [8000001.0, 2096000.32];
    }

    #[DataProvider('itsEdgeProvider')]
    public function test_golden_ci_its_bracket_edges(float $gross, float $expected): void
    {
        // ITS mensuel sur le brut : calculateIncomeTax(brut, 12, brut) —
        // le barème s'applique tel quel (CI_COMPLIANCE.md §1).
        $this->assertSame($expected, $this->rules()->calculateIncomeTax($gross, 12, $gross));
    }

    public function test_golden_ci_cnss_retirement_cap_exact_boundary(): void
    {
        // CNSS retraite 3,2 % sal. / 4,5 % pat. plafonnée à 1 647 315 XOF :
        //   à 1 647 315 exactement : salariale 52 714,08 · patronale 74 129,18
        //   à 2 000 000 : IDENTIQUES (plafond atteint) — la part famille/AT
        //   (plafonnée 70 000) reste aussi constante : 4 025 + 1 400.
        $rules = $this->rules();

        $atCap = $rules->calculateSocialCharges(1647315.0);
        $this->assertSame(52714.08, $atCap['employee']);
        $this->assertSame(79554.18, $atCap['employer']); // 74 129,18 + 4 025 + 1 400

        $above = $rules->calculateSocialCharges(2000000.0);
        $this->assertSame(52714.08, $above['employee']);
        $this->assertSame(79554.18, $above['employer']);
    }

    public function test_golden_ci_cnss_family_and_at_cap_exact_boundary(): void
    {
        // Famille 5,75 % + AT 2 % plafonnées à 70 000 XOF :
        //   à 70 000 exactement : famille 4 025 · AT 1 400
        //   à 100 000 : mêmes 4 025 + 1 400 (plafonnées) — seule la retraite
        //   progresse (2 240 → 3 200 salariale).
        $rules = $this->rules();

        $atCap = $rules->calculateSocialCharges(70000.0);
        $this->assertSame(2240.0, $atCap['employee']);
        $this->assertSame(8575.0, $atCap['employer']); // 3 150 + 4 025 + 1 400

        $above = $rules->calculateSocialCharges(100000.0);
        $this->assertSame(3200.0, $above['employee']);
        $this->assertSame(9925.0, $above['employer']); // 4 500 + 4 025 + 1 400
    }
}
