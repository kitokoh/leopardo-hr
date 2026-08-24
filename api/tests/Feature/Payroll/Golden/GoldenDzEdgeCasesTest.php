<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests DZ — cas limites complémentaires (issue #5244).
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/DZ_COMPLIANCE.md
 * §1-§2, constitution §III), pas reprise du code — une divergence = régression
 * de conformité. Volontairement SANS base de données (F-13) : les règles
 * retombent sur les barèmes par défaut quand tax_slabs est vide.
 *
 * Cas ajoutés (complément des 43 méthodes existantes #5149) :
 *   1. Bornes EXACTES de l'abattement IRG (CIDTA art. 104 bis) — plancher
 *      12 000 DZD/an et plafond 18 000 DZD/an atteints à la frontière.
 *   2. Profils réalistes DZ (fixtures) : SMIG, ouvrier qualifié, cadre
 *      confirmé, direction — CNAS + IRG + net complets.
 */
class GoldenDzEdgeCasesTest extends TestCase
{
    private function rules(): AlgeriaPayrollRules
    {
        return new AlgeriaPayrollRules;
    }

    /**
     * Borne plancher de l'abattement : 40 % de l'impôt ANNUEL = 12 000 DZD
     * exactement quand l'impôt mensuel avant abattement vaut 2 500 DZD
     * (2 500 × 12 × 40 % = 12 000) — la frontière plancher/proportionnel.
     *
     * Calcul manuel, assiette IRG mensuelle 30 869,57 DZD (tranche 23 %) :
     *   (30 869,57 − 20 000) × 23 % = 2 500,00 DZD/mois
     *   annuel = 30 000,00 → abattement 40 % = 12 000,00 (exactement au
     *   plancher, ni plancher ni proportionnel ne s'écartent)
     *   IRG = (30 000,00 − 12 000,00) / 12 = 1 500,00 DZD/mois
     */
    public function test_golden_dz_abatement_floor_exact_boundary(): void
    {
        $this->assertSame(1500.0, $this->rules()->calculateIncomeTax(30869.57));
    }

    /**
     * Borne plafond de l'abattement : 40 % de l'impôt ANNUEL = 18 000 DZD
     * exactement quand l'impôt mensuel avant abattement vaut 3 750 DZD
     * (3 750 × 12 × 40 % = 18 000) — la frontière proportionnel/plafond.
     *
     * Calcul manuel, assiette IRG mensuelle 36 304,35 DZD (tranche 23 %) :
     *   (36 304,35 − 20 000) × 23 % = 3 750,00 DZD/mois
     *   annuel = 45 000,00 → abattement 40 % = 18 000,00 (exactement au
     *   plafond)
     *   IRG = (45 000,00 − 18 000,00) / 12 = 2 250,00 DZD/mois
     */
    public function test_golden_dz_abatement_cap_exact_boundary(): void
    {
        $this->assertSame(2250.0, $this->rules()->calculateIncomeTax(36304.35));
    }

    /**
     * Fixtures réalistes DZ — profils complets (CNAS + IRG + net).
     *
     * @return iterable<string, array{0: float, 1: float, 2: float, 3: float, 4: float}>
     */
    public static function dzProfileProvider(): iterable
    {
        yield 'SMIG (20 000)' => [20000.0, 1800.0, 5200.0, 0.0, 18200.0];
        yield 'Ouvrier qualifié (35 000)' => [35000.0, 3150.0, 9100.0, 1635.30, 30214.70];
        yield 'Cadre confirmé (80 000)' => [80000.0, 7200.0, 20800.0, 11956.00, 60844.00];
        yield 'Direction (500 000)' => [500000.0, 45000.0, 130000.0, 137950.00, 317050.00];
    }

    #[DataProvider('dzProfileProvider')]
    public function test_golden_dz_profile(
        float $gross,
        float $expectedCnasEmployee,
        float $expectedCnasEmployer,
        float $expectedIrg,
        float $expectedNet,
    ): void {
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges($gross);
        $this->assertSame($expectedCnasEmployee, $charges['employee']);
        $this->assertSame($expectedCnasEmployer, $charges['employer']);

        // Assiette IRG = brut − CNAS salariale (DZ_COMPLIANCE.md §1bis).
        $taxable = $gross - $charges['employee'];
        $irg = $rules->calculateIncomeTax($taxable);
        $this->assertSame($expectedIrg, $irg);

        $this->assertSame($expectedNet, round($gross - $charges['employee'] - $irg, 2));
    }

    public function test_golden_dz_profile_smig_detail(): void
    {
        // SMIG 20 000 DZD : CNAS salariale 9 % = 1 800 → assiette IRG 18 200
        // → tranche 0 % (≤ 20 000) → IRG 0 → net 18 200,00.
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(20000.0);

        $this->assertSame(1800.0, $charges['employee']);
        $this->assertSame(18200.0, 20000.0 - $charges['employee']);
        $this->assertSame(0.0, $rules->calculateIncomeTax(18200.0));
        $this->assertSame(18200.0, round(20000.0 - $charges['employee'], 2));
    }

    public function test_golden_dz_profile_cadre_80000_detail(): void
    {
        // Cadre confirmé 80 000 DZD : CNAS salariale 7 200 → assiette 72 800.
        // IRG : 4 600 (20-40k) + 32 800 × 27 % = 8 856 → 13 456/mois
        // → annuel 161 472 → abattement 40 % = 64 588,80 → plafonné 18 000
        // → IRG = (161 472 − 18 000) / 12 = 11 956,00 · net = 60 844,00.
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(80000.0);

        $this->assertSame(7200.0, $charges['employee']);
        $irg = $rules->calculateIncomeTax(80000.0 - $charges['employee']);
        $this->assertSame(11956.0, $irg);
        $this->assertSame(60844.0, round(80000.0 - $charges['employee'] - $irg, 2));
    }

    public function test_golden_dz_profile_direction_500000_detail(): void
    {
        // Direction 500 000 DZD : CNAS salariale 45 000 → assiette 455 000.
        // IRG : 4 600 + 10 800 + 24 000 + 52 800 + 135 000 × 35 % = 47 250
        // → 139 450/mois → annuel 1 673 400 → abattement plafonné 18 000
        // → IRG = (1 673 400 − 18 000) / 12 = 137 950,00 · net = 317 050,00.
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(500000.0);

        $this->assertSame(45000.0, $charges['employee']);
        $irg = $rules->calculateIncomeTax(500000.0 - $charges['employee']);
        $this->assertSame(137950.0, $irg);
        $this->assertSame(317050.0, round(500000.0 - $charges['employee'] - $irg, 2));
    }

    public function test_golden_dz_irg_assiette_is_net_of_cnas(): void
    {
        // DZ_COMPLIANCE.md §1bis : l'IRG se calcule sur brut − CNAS salariale.
        // Un salaire de 60 000 DZD donne une assiette de 54 600 (et non 60 000) :
        // IRG(54 600) = 7 042 (F-03) — l'écart par rapport à IRG(60 000) = 8 500
        // prouve que l'assiette nette est bien appliquée.
        $rules = $this->rules();

        $this->assertSame(7042.0, $rules->calculateIncomeTax(54600.0));
        $this->assertSame(8500.0, $rules->calculateIncomeTax(60000.0));
    }
}
