<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use PHPUnit\Framework\TestCase;

/**
 * Issue #1827 — Sénégal (SN) : SenegalPayrollRules « pilot » vers prêt pour
 * « production » — TRIMF, CFCE, IPRES T1 plafonné + T2 cadres, abattement
 * 30 %. Volontairement SANS base de données (méthodologie golden F-03/F-13).
 * Référence légale : docs/payroll/SN_COMPLIANCE.md.
 */
class SenegalRulesUnitTest extends TestCase
{
    private function sn(): SenegalPayrollRules
    {
        return new SenegalPayrollRules;
    }

    public function test_trimf_all_6_brackets(): void
    {
        // Calcul manuel (docs/payroll/SN_COMPLIANCE.md §3) — table TRIMF
        // forfaitaire mensuelle par tranche de brut :
        //   0-25k : 900 · 25 001-75k : 2 700 · 75 001-150k : 5 400
        //   150 001-350k : 9 000 · 350 001-700k : 18 000 · > 700k : 36 000
        $rules = $this->sn();

        self::assertSame(900.0, $rules->calculateBracketTax(25000.0));
        self::assertSame(2700.0, $rules->calculateBracketTax(25001.0));
        self::assertSame(2700.0, $rules->calculateBracketTax(75000.0));
        self::assertSame(5400.0, $rules->calculateBracketTax(75001.0));
        self::assertSame(5400.0, $rules->calculateBracketTax(150000.0));
        self::assertSame(9000.0, $rules->calculateBracketTax(150001.0));
        self::assertSame(9000.0, $rules->calculateBracketTax(350000.0));
        self::assertSame(18000.0, $rules->calculateBracketTax(350001.0));
        self::assertSame(18000.0, $rules->calculateBracketTax(700000.0));
        self::assertSame(36000.0, $rules->calculateBracketTax(700001.0));
        self::assertSame(36000.0, $rules->calculateBracketTax(2000000.0));
    }

    public function test_cfce_in_social_contributions(): void
    {
        // CFCE — Contribution Forfaitaire à la Charge de l'Employeur
        // (docs/payroll/SN_COMPLIANCE.md §5) : 3 % patronal sur masse brute.
        $codes = array_column($this->sn()->socialContributions(), 'code');

        self::assertContains('CFCE_SN_PAT', $codes);

        $cfce = collect($this->sn()->socialContributions())->firstWhere('code', 'CFCE_SN_PAT');
        self::assertSame('employer', $cfce['type']);
        self::assertSame(3.0, $cfce['rate']);
        self::assertNull($cfce['cap']);
    }

    public function test_ipres_t1_capped_at_432k(): void
    {
        // Calcul manuel (docs/payroll/SN_COMPLIANCE.md §4) :
        //   brut 432 000 → IPRES salariale 5,6 % × 432 000 = 24 192 (T2 non
        //   déclenché à la borne exacte) ;
        //   brut 500 000 → T1 plafonné à 24 192 + T2 2,4 % × 68 000 = 1 632
        //   → salariale totale 25 824.
        self::assertSame(24192.0, $this->sn()->calculateSocialCharges(432000.0)['employee']);
        self::assertSame(25824.0, $this->sn()->calculateSocialCharges(500000.0)['employee']);
    }

    public function test_ipres_t2_for_cadre(): void
    {
        // Calcul manuel (docs/payroll/SN_COMPLIANCE.md §4bis) — brut 1 000 000 :
        //   T1 salarié 5,6 % × 432 000 = 24 192 · T1 patronal 8,4 % × 432 000 = 36 288
        //   Tranche T2 = 1 000 000 − 432 000 = 568 000
        //   T2 salarié 2,4 % × 568 000 = 13 632 · T2 patronal 3,6 % × 568 000 = 20 448
        //   CSS famille 3 % = 30 000 · CSS AT 1 % = 10 000 · CFCE 3 % = 30 000
        //   → salarié 37 824 · patronal 126 736
        $charges = $this->sn()->calculateSocialCharges(1000000.0);

        self::assertSame(37824.0, $charges['employee']);
        self::assertSame(126736.0, $charges['employer']);
    }

    public function test_professional_expenses_30_percent(): void
    {
        $abatement = $this->sn()->professionalExpensesDeduction();

        // 30 % du brut, NON plafonné (docs/payroll/SN_COMPLIANCE.md §6).
        self::assertSame(30.0, $abatement['rate']);
        self::assertNull($abatement['cap']);

        self::assertSame(90000.0, 300000.0 * 0.30);
    }
}
