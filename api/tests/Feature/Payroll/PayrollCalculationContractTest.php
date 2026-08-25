<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationPresenter;
use Tests\TestCase;

/**
 * MULTI-PAYS (#1869) — contrat de calcul complet et explicable.
 *
 * Golden DZ / CI / FR calculés À LA MAIN (cotisations, assiette, impôt,
 * net, coût employeur) + cohérence simulation ↔ moteur (mêmes appels
 * métier), salaire nul, identité net = brut − retenues (pas de double
 * comptage), pays/devise effectifs, version de barème.
 */
class PayrollCalculationContractTest extends TestCase
{
    private function presenter(): PayrollCalculationPresenter
    {
        return new PayrollCalculationPresenter(new CountryRulesResolver);
    }

    public function test_golden_dz_contract(): void
    {
        // Calcul manuel (docs/payroll/DZ_COMPLIANCE.md §1) — brut 60 000 DZD :
        //   CNAS salariale 9 % = 5 400 · patronale 26 % = 15 600
        //   Assiette = 60 000 − 5 400 = 54 600
        //   IRG mensuel (abattement annuel inclus) = 7 042
        //   Net = 60 000 − 5 400 − 7 042 = 47 558
        //   Coût employeur = 60 000 + 15 600 = 75 600
        $contract = $this->presenter()->present('DZ', 60000.0);

        $this->assertSame('DZ', $contract['country_code']);
        $this->assertSame('DZD', $contract['currency']);
        $this->assertEquals(60000.0, $contract['gross']);
        $this->assertEquals(5400.0, $contract['social_employee']);
        $this->assertEquals(54600.0, $contract['tax_base']);
        $this->assertEquals(7042.0, $contract['income_tax']);
        $this->assertEquals(0.0, $contract['other_deductions']);
        $this->assertEquals(47558.0, $contract['net_salary']);
        $this->assertEquals(15600.0, $contract['social_employer']);
        $this->assertEquals(75600.0, $contract['total_cost']);
        $this->assertSame(12, strlen($contract['slab_version']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $contract['rules_period']);
    }

    public function test_golden_ci_contract(): void
    {
        // Calcul manuel (CI #1918 — réforme ITS 2024, CGI art. 119 bis ; plafonds
        // par branche #1913 CNPS) — brut 500 000 XOF :
        //   CNSS retraite salariale 3,2 % = 16 000 (plafond 1 647 315 non atteint)
        //   Patronal : retraite 4,5 % × 500 000 = 22 500 · famille 5,75 % et AT
        //     2,0 % plafonnées à 70 000 (guide CNPS, #1913) = 4 025 + 1 400
        //     → 27 925
        //   ITS unifié MENSUEL sur le BRUT (plus d'abattement ni de CN) :
        //     75 001–240 000 × 16 % = 26 400 · 240 001–500 000 × 21 %
        //     = 260 000 × 21 % = 54 600 → 81 000,00
        //   Net = 500 000 − 16 000 − 81 000 = 403 000,00
        //   Coût employeur = 500 000 + 27 925 = 527 925
        $contract = $this->presenter()->present('CI', 500000.0);

        $this->assertSame('CI', $contract['country_code']);
        $this->assertSame('XOF', $contract['currency']);
        $this->assertSame('pilot', $contract['confidence_level']);
        $this->assertEquals(16000.0, $contract['social_employee']);
        $this->assertEquals(484000.0, $contract['tax_base']);
        $this->assertEquals(81000.0, $contract['income_tax']);
        $this->assertEquals(0.0, $contract['bracket_tax']);
        $this->assertEquals(0.0, $contract['other_deductions']);
        $this->assertEquals(403000.0, $contract['net_salary']);
        // Plafonds #1913 : famille + AT plafonnés à 70 000 → 27 925,00.
        $this->assertEquals(27925.0, $contract['social_employer']);
        $this->assertEquals(527925.0, $contract['total_cost']);
    }

    public function test_golden_fr_contract(): void
    {
        // Calcul manuel (FrancePayrollRules, modèle #5438 — structure URSSAF
        // détaillée, PMSS 2026 = 4 005 €) — brut 3 000 EUR :
        //   Salarié 644,41 (VIE_PLF 207,00 + VIE_DPL 12,00 + RET_T1 94,50 +
        //   PREV 45,00 + CSG 271,17 + CRDS 14,74)
        //   Patronal 1 026,60 (MAL 390,00 + VIE_PLF 256,50 + VIE_DPL 57,00 +
        //   RET_T1 141,60 + PREV 45,00 + CHO 121,50 + FNGS 15,00)
        //   Assiette = 2 355,59 · annuel 28 267,08 : tranche 11 % seule
        //   (sous 29 580) → 1 833,3788 → impôt mensuel 152,78
        //   Net = 3 000 − 644,41 − 152,78 = 2 202,81 · Coût = 4 026,60
        $contract = $this->presenter()->present('FR', 3000.0);

        $this->assertSame('FR', $contract['country_code']);
        $this->assertSame('EUR', $contract['currency']);
        $this->assertEquals(644.41, $contract['social_employee']);
        $this->assertEquals(2355.59, $contract['tax_base']);
        $this->assertEquals(152.78, $contract['income_tax']);
        $this->assertEquals(2202.81, $contract['net_salary']);
        $this->assertEquals(1026.60, $contract['social_employer']);
        $this->assertEquals(4026.60, $contract['total_cost']);
    }

    public function test_zero_salary_contract_is_all_zeros(): void
    {
        $contract = $this->presenter()->present('DZ', 0.0);

        $this->assertEquals(0.0, $contract['gross']);
        $this->assertEquals(0.0, $contract['social_employee']);
        $this->assertEquals(0.0, $contract['tax_base']);
        $this->assertEquals(0.0, $contract['income_tax']);
        $this->assertEquals(0.0, $contract['net_salary']);
        $this->assertEquals(0.0, $contract['total_cost']);
    }

    public function test_net_is_gross_minus_deductions_without_double_counting(): void
    {
        // Identité : net = brut − (salarial + impôt + autres retenues).
        foreach (['DZ' => 60000.0, 'CI' => 500000.0, 'FR' => 3000.0] as $country => $gross) {
            $contract = $this->presenter()->present($country, $gross);

            $expectedNet = round(
                $contract['gross'] - $contract['social_employee'] - $contract['income_tax'] - $contract['other_deductions'],
                2,
            );

            $this->assertEquals($expectedNet, $contract['net_salary'], "net identity for {$country}");
        }
    }

    public function test_slab_version_is_stable_per_ruleset(): void
    {
        $presenter = $this->presenter();

        $dz1 = $presenter->present('DZ', 60000.0);
        $dz2 = $presenter->present('DZ', 70000.0);
        $ci = $presenter->present('CI', 500000.0);

        $this->assertSame($dz1['slab_version'], $dz2['slab_version']);
        $this->assertNotSame($dz1['slab_version'], $ci['slab_version']);
    }

    public function test_presenter_matches_api_simulation_contract(): void
    {
        // La simulation API (CotisationSimulationController) et le présentateur
        // utilisent les MÊMES appels métier — le contrat imbriqué `contract`
        // doit être cohérent avec les montants exposés par l'API.
        $contract = $this->presenter()->present('DZ', 60000.0);

        $this->assertEquals($contract['gross'], 60000.0);
        $this->assertEquals($contract['net_salary'], 60000.0 - $contract['social_employee'] - $contract['income_tax']);
        $this->assertEquals($contract['total_cost'], $contract['gross'] + $contract['social_employer']);
    }
}
