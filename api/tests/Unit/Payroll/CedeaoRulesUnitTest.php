<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use PHPUnit\Framework\TestCase;

/**
 * Issue #1825 — CEDEAO/CI : CedeaoPayrollRules passe de « placeholder » à
 * « pilot » pour la Côte d'Ivoire (ITSAS CGI 2024 art. 116-120, CN,
 * CNSS 2024, 13ème mois, préavis art. 18). Volontairement SANS base de
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

    public function test_ci_itsas_and_cn_calculated_separately(): void
    {
        $rules = $this->ci();

        // Calcul manuel (docs/payroll/CI_COMPLIANCE.md §1-§3) — assiette
        // mensuelle 200 000 → annuelle 2 400 000 :
        //   tranche 0 % (0-600k) = 0 · tranche 2 % (600k-2M) = 28 000
        //   tranche 21 % (2M-2,4M) = 84 000 → ITSAS annuel 112 000 → mensuel 9 333,33
        $itsas = $rules->calculateIncomeTax(200000.0);
        $this->assertSame(9333.33, $itsas);

        // CN sur BRUT 300 000 : max(0, 300 000 − 50 000) × 1,5 % = 3 750
        $cn = $rules->calculateBracketTax(300000.0);
        $this->assertSame(3750.0, $cn);

        // Impôt total mensuel = ITSAS + CN = 13 083,33
        $this->assertSame(13083.33, round($itsas + $cn, 2));

        // Tranche 0 % : assiette 100 000 → ITSAS mensuel 1 000 ; SMIG
        // (brut 75 000) → CN 375.
        $this->assertSame(1000.0, $rules->calculateIncomeTax(100000.0));
        $this->assertSame(375.0, $rules->calculateBracketTax(75000.0));
    }

    public function test_ci_cnss_cap_at_1647315(): void
    {
        // Calcul manuel (docs/payroll/CI_COMPLIANCE.md §4) — brut 2 000 000 :
        //   retraite salariale 3,2 % × min(2M, 1 647 315) = 52 714,08
        //   retraite patronale 4,5 % × 1 647 315 = 74 129,18
        //   famille patronale 5,75 % × 1 647 315 = 94 720,61
        //   AT patronale 2,0 % × 2M (non plafonné) = 40 000
        //   → salarié 52 714,08 · patronal 208 849,79
        $charges = $this->ci()->calculateSocialCharges(2000000.0);

        $this->assertSame(52714.08, $charges['employee']);
        $this->assertSame(208849.79, $charges['employer']);

        // Brut sous plafond : 1 000 000 → salarié 32 000 · patronal 122 500.
        $chargesBelow = $this->ci()->calculateSocialCharges(1000000.0);
        $this->assertSame(32000.0, $chargesBelow['employee']);
        $this->assertSame(122500.0, $chargesBelow['employer']);
    }

    public function test_ci_abatement_20_percent(): void
    {
        $abatement = $this->ci()->professionalExpensesDeduction();

        // 20 % du brut, NON plafonné (docs/payroll/CI_COMPLIANCE.md §2).
        $this->assertSame(20.0, $abatement['rate']);
        $this->assertNull($abatement['cap']);

        // Sans plafond, l'abattement = 20 % du brut quel que soit le niveau.
        $this->assertSame(40000.0, 200000.0 * 0.20);
        $this->assertSame(200000.0, 1000000.0 * 0.20);
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
        $this->assertCount(5, $rules->taxSlabs());

        // Palier HS CI (art. 21) : 1.15 / 1.35 / 1.50.
        $tiers = $rules->overtimeRateTiers();
        $this->assertSame([['up_to_hours' => 8.0, 'multiplier' => 1.15], ['up_to_hours' => 14.0, 'multiplier' => 1.35], ['up_to_hours' => null, 'multiplier' => 1.50]], $tiers);
    }

    public function test_other_uemoa_members_unaffected(): void
    {
        // Les membres ML/BF/BJ/TG/NE ne doivent PAS hériter des règles CI.
        foreach (['ML', 'BF', 'BJ', 'TG', 'NE'] as $memberCode) {
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
