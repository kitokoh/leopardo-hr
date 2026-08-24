<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests Cameroun (CM) — cas limites (issue #5252).
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/CM_COMPLIANCE.md,
 * constitution §III). Complément des 17 tests existants :
 *   1. IRPP : profils franchissant les bornes annuelles 2 M / 3 M / 5 M XAF
 *      (CGI 2024 art. 68 : 10/15/25/35 %) avec abattement frais pro 30 %
 *      plafonné 350 000 XAF/mois (CGI 2024) + centimes additionnels ×1,10.
 *   2. Plafond d'abattement EFFECTIVEMENT atteint (brut 1 200 000).
 *   3. CNPS : preuve que l'AT 2 % reste NON plafonné au-delà du plafond
 *      statutaire 750 000 (vieillesse + famille plafonnées).
 */
class GoldenCmEdgeCasesTest extends TestCase
{
    private function rules(): CemacPayrollRules
    {
        return new CemacPayrollRules('CM');
    }

    /**
     * Profils IRPP CM — calcul manuel (CM_COMPLIANCE.md §1-§2) :
     *   CNPS salariale 4,2 % (plafond 750 000) → assiette = brut − CNPS
     *   abattement 30 % du BRUT plafonné 350 000/mois
     *   annuel = (assiette − abattement) × 12 → barème 10/15/25/35 %
     *   mensuel = annuel/12 → × 1,10 (centimes additionnels).
     *
     * @return iterable<string, array{0: float, 1: float}>
     */
    public static function irppProfileProvider(): iterable
    {
        // brut 250 000 : CNPS 10 500 → assiette 239 500 ; abattement 75 000
        //   → annuel 1 974 000 → tranche 10 % : 197 400 → 16 450 × 1,1 = 18 095,00
        yield 'sous 2 M annuel (250 000)' => [250000.0, 18095.00];
        // brut 300 000 : CNPS 12 600 → 287 400 ; abattement 90 000 → annuel
        //   2 368 800 → 200 000 + 368 800×15 % = 255 320 → 21 276,67 × 1,1 = 23 404,33
        yield 'franchit 2 M annuel (300 000)' => [300000.0, 23404.33];
        // brut 500 000 : CNPS 21 000 → 479 000 ; abattement 150 000 → annuel
        //   3 948 000 → 200 000 + 150 000 + 948 000×25 % = 587 000 → 48 916,67 × 1,1 = 53 808,33
        yield 'franchit 3 M annuel (500 000)' => [500000.0, 53808.33];
        // brut 800 000 : CNPS plafonnée 31 500 → 768 500 ; abattement 240 000
        //   → annuel 6 342 000 → 200 000 + 150 000 + 500 000 + 1 342 000×35 %
        //   = 1 319 700 → 109 975 × 1,1 = 120 972,50
        yield 'franchit 5 M annuel (800 000)' => [800000.0, 120972.50];
        // brut 1 200 000 : abattement 30 % = 360 000 → PLAFONNÉ 350 000
        //   (le plafond s'applique) ; CNPS 31 500 → assiette 1 168 500 − 350 000
        //   = 818 500 → annuel 9 822 000 → 200 000 + 150 000 + 500 000
        //   + 4 822 000×35 % = 2 537 700 → 211 475 × 1,1 = 232 622,50
        yield 'abattement plafonné (1 200 000)' => [1200000.0, 232622.50];
    }

    #[DataProvider('irppProfileProvider')]
    public function test_golden_cm_irpp_profiles(float $gross, float $expected): void
    {
        $cnss = $this->rules()->calculateSocialCharges($gross)['employee'];
        $this->assertSame($expected, $this->rules()->calculateIncomeTax($gross - $cnss, 12, $gross));
    }

    public function test_golden_cm_cnps_at_remains_uncapped_above_statutory_ceiling(): void
    {
        // CNPS : vieillesse 4,2 % sal. / 4,2 % pat. + famille 7 % plafonnées à
        //   750 000 ; AT 2 % NON plafonné :
        //   brut 750 000 → salariale 31 500 · patronale 31 500 + 52 500 + 15 000 = 99 000
        //   brut 1 000 000 → salariale 31 500 (plafond) · patronale 31 500
        //     + 52 500 + 20 000 (AT sur le brut entier) = 104 000
        //   brut 2 000 000 → AT 40 000 (preuve du non-plafonnement de l'AT).
        $rules = $this->rules();

        $at750 = $rules->calculateSocialCharges(750000.0);
        $this->assertSame(31500.0, $at750['employee']);
        $this->assertSame(99000.0, $at750['employer']);

        $at1M = $rules->calculateSocialCharges(1000000.0);
        $this->assertSame(31500.0, $at1M['employee']);
        $this->assertSame(104000.0, $at1M['employer']);

        $at2M = $rules->calculateSocialCharges(2000000.0);
        $this->assertSame(31500.0, $at2M['employee']);
        $this->assertSame(124000.0, $at2M['employer']); // 31 500 + 52 500 + 40 000
    }
}
