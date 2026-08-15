<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use PHPUnit\Framework\TestCase;

/**
 * Issue #1825 — CEDEAO/CI : CedeaoPayrollRules passe de « placeholder » à
 * « pilot » pour la Côte d'Ivoire (ITS 2024 unifié art. 119 bis, CNSS,
 * 13ème mois, préavis art. 18). Volontairement SANS base de
 * données : les règles retombent sur les barèmes par défaut quand
 * tax_slabs/social_contributions sont vides (méthodologie golden F-03/F-13).
 * Référence légale : docs/payroll/CI_COMPLIANCE.md.
 */
class CedeaoRulesUnitTest extends TestCase
{
    private function ci(): CedeaoPayrollRules
    {
        return new CedeaoPayrollRules('CI');
    }

    public function test_ci_its_2024_calculated_on_monthly_gross(): void
    {
        $rules = $this->ci();

        // Calcul manuel (docs/payroll/CI_COMPLIANCE.md §1 — réforme 2024,
        // ord. 2023-718/719, CGI art. 119 bis) : ITS unique mensuel sur le
        // BRUT — tranche 75 001–240 000 @ 16 % → 200 000 : 125 000 × 16 % = 20 000.
        $its = $rules->calculateIncomeTax(200000.0);
        $this->assertSame(20000.0, $its);

        // CN abolie (#1918, fusionnée dans l'ITS) : bracket tax = 0.
        $cn = $rules->calculateBracketTax(300000.0);
        $this->assertSame(0.0, $cn);

        // Tranche 0 % : assiette 75 000 (SMIG CI) → ITS 0.
        $this->assertSame(0.0, $rules->calculateIncomeTax(75000.0));
        $this->assertSame(0.0, $rules->calculateBracketTax(75000.0));
    }

    public function test_ci_cnss_cap_at_1647315(): void
    {
        // Calcul manuel (docs/payroll/CI_COMPLIANCE.md §4 + #1913) — brut 2 000 000 :
        //   retraite salariale 3,2 % × min(2M, 1 647 315) = 52 714,08
        //   retraite patronale 4,5 % × 1 647 315 = 74 129,18
        //   famille patronale 5,75 % × 70 000 (plafond branche #1913) = 4 025,00
        //   AT patronale 2,0 % × 70 000 = 1 400,00
        //   → salarié 52 714,08 · patronal 79 554,18
        $charges = $this->ci()->calculateSocialCharges(2000000.0);

        $this->assertSame(52714.08, $charges['employee']);
        $this->assertSame(79554.18, $charges['employer']);

        // Brut 1 000 000 : famille/AT plafonnées à 70 000 → patronal
        // 45 000 + 4 025 + 1 400 = 50 425,00.
        $chargesBelow = $this->ci()->calculateSocialCharges(1000000.0);
        $this->assertSame(32000.0, $chargesBelow['employee']);
        $this->assertSame(50425.0, $chargesBelow['employer']);
    }

    public function test_ci_abatement_supprime_2024(): void
    {
        $abatement = $this->ci()->professionalExpensesDeduction();

        // Réforme ITS 2024 (#1918) : plus d'abattement frais pro — l'ITS
        // s'applique sur le BRUT (CGI art. 119 bis, ord. 2023-718/719).
        $this->assertSame(0.0, $abatement['rate']);
        $this->assertNull($abatement['cap']);
    }

    public function test_ci_thirteenth_month_mandatory(): void
    {
        // Pratique généralisée via conventions de branche (CI_COMPLIANCE.md §9).
        $this->assertTrue($this->ci()->thirteenthMonthMandatory());
        $this->assertSame('fully_taxable', $this->ci()->thirteenthMonthTaxTreatment());
    }

    public function test_ci_notice_period_employee_level(): void
    {
        // Code du travail CI art. 18 — niveau employé/technicien
        // (CI_COMPLIANCE.md §8) : < 5 ans → 30 j ; ≥ 5 ans → 60 j.
        $this->assertSame(30.0, $this->ci()->noticePeriodDays(3.0));
        $this->assertSame(30.0, $this->ci()->noticePeriodDays(4.9));
        $this->assertSame(60.0, $this->ci()->noticePeriodDays(5.0));
        $this->assertSame(60.0, $this->ci()->noticePeriodDays(12.0));
    }

    public function test_ci_exposes_pilot_metadata(): void
    {
        $rules = $this->ci();

        $this->assertSame('CI', $rules->countryCode());
        $this->assertSame('pilot', $rules->confidenceLevel());
        $this->assertSame(75000.0, $rules->minimumWage());
        $this->assertCount(6, $rules->taxSlabs());

        // Palier HS CI (art. 21) : 1.15 / 1.35 / 1.50.
        $tiers = $rules->overtimeRateTiers();
        $this->assertSame([['up_to_hours' => 8.0, 'multiplier' => 1.15], ['up_to_hours' => 14.0, 'multiplier' => 1.35], ['up_to_hours' => null, 'multiplier' => 1.50]], $tiers);
    }

    public function test_other_uemoa_members_unaffected(): void
    {
        // Les membres BJ/TG/NE ne doivent PAS hériter des règles CI. BF et ML
        // ont leurs propres barèmes pilot (#1829) — testés dans
        // test_cedeao_members_pilot_metadata (PayrollCountryRulesTest).
        foreach (['BJ', 'TG', 'NE'] as $memberCode) {
            $rules = (new CedeaoPayrollRules)->forMemberCountry($memberCode);

            $this->assertSame('placeholder', $rules->confidenceLevel(), "{$memberCode} doit rester placeholder");
            $this->assertCount(5, $rules->taxSlabs(), "{$memberCode} doit garder le barème placeholder générique");
            $this->assertSame(0.0, $rules->calculateBracketTax(1000000.0), "{$memberCode} n'a pas de CN ivoirienne");
            $this->assertFalse($rules->thirteenthMonthMandatory(), "{$memberCode} : 13ème mois non obligatoire");

            // Codes CNSS génériques, pas les codes CI.
            $codes = array_column($rules->socialContributions(), 'code');
            $this->assertNotContains('CNSS_CI_RET_EMP', $codes, "{$memberCode} ne doit pas exposer les codes CNSS CI");
        }

        // BJ conserve le comportement placeholder (3,6 % / 16,4 % non plafonnés).
        $bjCharges = (new CedeaoPayrollRules)->forMemberCountry('BJ')->calculateSocialCharges(1000.0);
        $this->assertSame(36.0, $bjCharges['employee']);
        $this->assertSame(164.0, $bjCharges['employer']);
    }
}
