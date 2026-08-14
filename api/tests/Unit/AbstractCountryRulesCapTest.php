<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use PHPUnit\Framework\TestCase;

/**
 * ZONE-INFRA (#1820) — mécanismes transversaux Afrique sub-saharienne.
 *
 * 1. computeContribution() : plafonds statutaires appliqués dans
 *    calculateSocialCharges() (CM 750 000 XAF, CI 1 647 315 XOF,
 *    SN 432 000 XOF) — un brut au-dessus du plafond est assis sur le
 *    plafond, jamais sur le brut complet.
 * 2. Nouveaux contrats d'interface avec défauts inoffensifs :
 *    professionalExpensesDeduction() (0 %), calculateBracketTax() (0.0),
 *    thirteenthMonthMandatory() (false) / thirteenthMonthTaxTreatment()
 *    ('fully_taxable'), familyAllowancePerChild() (0.0) — aucun impact sur
 *    DZ/MA/TN/FR/TR existants.
 *
 * Tests purs (pas de DB) : les règles retombent sur les taux/plafonds par
 * défaut quand `social_contributions` est vide (comportement historique,
 * voir AbstractCountryRules::resolveContributionRate/Cap).
 */
class AbstractCountryRulesCapTest extends TestCase
{
    // ── Plafonds statutaires (computeContribution) ─────────────────────────

    public function test_senegal_contribution_capped_when_gross_above_432k(): void
    {
        $rules = new SenegalPayrollRules;

        // Brut 1 000 000 XOF > plafond 432 000 → assiette 432 000 :
        //   salariale : 432 000 × 5,6 % = 24 192,00
        //   patronale : 432 000 × (8,4 % + 3,0 %) = 432 000 × 11,4 % = 49 248,00
        $charges = $rules->calculateSocialCharges(1000000.0);

        $this->assertSame(24192.0, $charges['employee']);
        $this->assertSame(49248.0, $charges['employer']);
    }

    public function test_senegal_contribution_uncapped_when_gross_below_432k(): void
    {
        $rules = new SenegalPayrollRules;

        // Brut 200 000 XOF < plafond → assiette pleine :
        //   salariale : 200 000 × 5,6 % = 11 200,00
        //   patronale : 200 000 × 11,4 % = 22 800,00
        $charges = $rules->calculateSocialCharges(200000.0);

        $this->assertSame(11200.0, $charges['employee']);
        $this->assertSame(22800.0, $charges['employer']);
    }

    public function test_cameroon_cnps_capped_at_750k_xaf(): void
    {
        $rules = (new CemacPayrollRules)->forMemberCountry('CM');

        // Brut 2 000 000 XAF > plafond 750 000 → vieillesse/famille assises
        // sur 750 000 ; AT (2 %) non plafonné sur le brut complet (#1821) :
        //   salariale : 750 000 × 4,2 % = 31 500,00
        //   patronale : 750 000 × (4,2 % + 7,0 %) + 2 000 000 × 2,0 %
        //             = 84 000,00 + 40 000,00 = 124 000,00
        $charges = $rules->calculateSocialCharges(2000000.0);

        $this->assertSame(31500.0, $charges['employee']);
        $this->assertSame(124000.0, $charges['employer']);
    }

    public function test_other_cemac_members_keep_uncapped_placeholder(): void
    {
        // #1821 : seuls les membres passés en pilot reçoivent leur plafond —
        // GA/CG/CF/TD/GQ restent sur le placeholder non plafonné (#1824).
        $rules = (new CemacPayrollRules)->forMemberCountry('GA');

        $charges = $rules->calculateSocialCharges(2000000.0);

        $this->assertSame(84000.0, $charges['employee']);   // 2 000 000 × 4,2 %
        $this->assertSame(324000.0, $charges['employer']);  // 2 000 000 × 16,2 %
    }

    public function test_ivory_coast_cnss_capped_at_1647315_xof(): void
    {
        $rules = (new CedeaoPayrollRules)->forMemberCountry('CI');

        // Brut 3 000 000 XOF > plafond 1 647 315 → assiette 1 647 315 :
        //   salariale : 1 647 315 × 3,6 % = 59 303,34
        //   patronale : 1 647 315 × 16,4 % = 270 159,66
        $charges = $rules->calculateSocialCharges(3000000.0);

        $this->assertSame(59303.34, $charges['employee']);
        $this->assertSame(270159.66, $charges['employer']);
    }

    public function test_other_cedeao_members_keep_uncapped_placeholder(): void
    {
        // #1829 : BF/ML/BJ/TG/NE restent sur le placeholder non plafonné.
        $rules = (new CedeaoPayrollRules)->forMemberCountry('BJ');

        $charges = $rules->calculateSocialCharges(3000000.0);

        $this->assertSame(108000.0, $charges['employee']);  // 3 000 000 × 3,6 %
        $this->assertSame(492000.0, $charges['employer']);  // 3 000 000 × 16,4 %
    }

    public function test_algeria_no_cap_full_gross(): void
    {
        // DZ n'a pas de plafond statutaire → assiette = brut complet
        // (non-régression : refactor computeContribution sans changement).
        $rules = new AlgeriaPayrollRules;

        $charges = $rules->calculateSocialCharges(1000000.0);

        $this->assertSame(90000.0, $charges['employee']);   // 9 %
        $this->assertSame(260000.0, $charges['employer']);  // 26 %
    }

    // ── Nouveaux contrats d'interface — défauts inoffensifs ────────────────

    public function test_professional_expenses_deduction_default_is_zero(): void
    {
        $this->assertSame(
            ['rate' => 0.0, 'cap' => null],
            (new AlgeriaPayrollRules)->professionalExpensesDeduction()
        );
    }

    public function test_bracket_tax_default_is_zero(): void
    {
        $this->assertSame(0.0, (new AlgeriaPayrollRules)->calculateBracketTax(60000.0));
    }

    public function test_thirteenth_month_defaults_are_off_and_fully_taxable(): void
    {
        $rules = new AlgeriaPayrollRules;

        $this->assertFalse($rules->thirteenthMonthMandatory());
        $this->assertSame('fully_taxable', $rules->thirteenthMonthTaxTreatment());
    }

    public function test_family_allowance_default_is_zero(): void
    {
        $this->assertSame(0.0, (new AlgeriaPayrollRules)->familyAllowancePerChild());
    }
}
