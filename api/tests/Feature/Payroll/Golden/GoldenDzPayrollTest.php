<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie DZ.
 *
 * Méthodologie (docs/focus/PLAN.md, docs/payroll/DZ_COMPLIANCE.md) :
 * chaque valeur attendue est CALCULÉE À LA MAIN (tableur/calcul manuel),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Cas couverts (référence : docs/payroll/DZ_COMPLIANCE.md §1-§2) :
 *  1. SMIG (20 000 DZD)          → IRG 0, CNAS 1 800 / 5 200
 *  2. Cadre moyen (60 000 DZD)   → IRG 8 500, CNAS 5 400 / 15 600
 *  3. Haut salaire (350 000 DZD) → IRG 101 200, CNAS 31 500 / 91 000
 */
class GoldenDzPayrollTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function rules(): AlgeriaPayrollRules
    {
        return new AlgeriaPayrollRules();
    }

    public function test_golden_dz_irg_at_minimum_wage_20000(): void
    {
        // Calcul manuel : 20 000 DZD → tranche 0 % → impôt 0.
        $this->assertSame(0.0, $this->rules()->calculateIncomeTax(20000.0));
    }

    public function test_golden_dz_irg_at_60000(): void
    {
        // Calcul manuel (docs/payroll/DZ_COMPLIANCE.md §1) :
        //   0–20k : 0 · 20–40k : 20 000 × 23 % = 4 600 · 40–60k : 20 000 × 27 % = 5 400
        //   mensuel 10 000 → annuel 120 000 → abattement 40 % (plaf. 18 000) = 18 000
        //   IRG mensuel = (120 000 − 18 000) / 12 = 8 500
        $this->assertSame(8500.0, $this->rules()->calculateIncomeTax(60000.0));
    }

    public function test_golden_dz_irg_at_350000(): void
    {
        // Calcul manuel :
        //   0–20k : 0 · 20–40k : 4 600 · 40–80k : 10 800 · 80–160k : 24 000
        //   160–320k : 52 800 · 320–350k : 30 000 × 35 % = 10 500
        //   mensuel 102 700 → annuel 1 232 400 → abattement plafonné 18 000
        //   IRG mensuel = (1 232 400 − 18 000) / 12 = 101 200
        $this->assertSame(101200.0, $this->rules()->calculateIncomeTax(350000.0));
    }

    public function test_golden_dz_social_charges_at_60000(): void
    {
        // CNAS salariale 9 % = 5 400 · patronale 26 % = 15 600 (DZ_COMPLIANCE.md §2).
        $charges = $this->rules()->calculateSocialCharges(60000.0);

        $this->assertSame(5400.0, $charges['employee']);
        $this->assertSame(15600.0, $charges['employer']);
    }

    public function test_golden_dz_social_charges_at_minimum_wage(): void
    {
        // 20 000 × 9 % = 1 800 · 20 000 × 26 % = 5 200.
        $charges = $this->rules()->calculateSocialCharges(20000.0);

        $this->assertSame(1800.0, $charges['employee']);
        $this->assertSame(5200.0, $charges['employer']);
    }

    public function test_golden_dz_net_pay_at_60000(): void
    {
        // Net = brut − CNAS salariale − IRG = 60 000 − 5 400 − 8 500 = 46 100.
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(60000.0);
        $net = 60000.0 - $charges['employee'] - $rules->calculateIncomeTax(60000.0);

        $this->assertSame(46100.0, $net);
    }
}
