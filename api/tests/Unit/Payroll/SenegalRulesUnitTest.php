<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\TestCase;
use Tests\Support\SnPayrollFixtures;

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

        self::assertSame(SnPayrollFixtures::bracketTax(25000.0), $rules->calculateBracketTax(25000.0));
        self::assertSame(SnPayrollFixtures::bracketTax(25001.0), $rules->calculateBracketTax(25001.0));
        self::assertSame(SnPayrollFixtures::bracketTax(75000.0), $rules->calculateBracketTax(75000.0));
        self::assertSame(SnPayrollFixtures::bracketTax(75001.0), $rules->calculateBracketTax(75001.0));
        self::assertSame(SnPayrollFixtures::bracketTax(150000.0), $rules->calculateBracketTax(150000.0));
        self::assertSame(SnPayrollFixtures::bracketTax(150001.0), $rules->calculateBracketTax(150001.0));
        self::assertSame(SnPayrollFixtures::bracketTax(350000.0), $rules->calculateBracketTax(350000.0));
        self::assertSame(SnPayrollFixtures::bracketTax(350001.0), $rules->calculateBracketTax(350001.0));
        self::assertSame(SnPayrollFixtures::bracketTax(700000.0), $rules->calculateBracketTax(700000.0));
        self::assertSame(SnPayrollFixtures::bracketTax(700001.0), $rules->calculateBracketTax(700001.0));
        self::assertSame(SnPayrollFixtures::bracketTax(2000000.0), $rules->calculateBracketTax(2000000.0));
    }

    public function test_cfce_in_social_contributions(): void
    {
        // CFCE — Contribution Forfaitaire à la Charge de l'Employeur
        // (docs/payroll/SN_COMPLIANCE.md §5) : 3 % patronal sur masse brute.
        $codes = array_column($this->sn()->socialContributions(), 'code');

        self::assertContains('CFCE_SN_PAT', $codes);

        $cfce = collect($this->sn()->socialContributions())->firstWhere('code', 'CFCE_SN_PAT');
        self::assertNotNull($cfce);
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
        self::assertSame(SnPayrollFixtures::socialCharges(432000.0)['employee'], $this->sn()->calculateSocialCharges(432000.0)['employee']);
        self::assertSame(25824.0, $this->sn()->calculateSocialCharges(500000.0)['employee']);
    }

    public function test_ipres_t2_for_cadre(): void
    {
        // Calcul manuel (docs/payroll/SN_COMPLIANCE.md §4bis) — brut 1 000 000 :
        //   T1 salarié 5,6 % × 432 000 = 24 192 · T1 patronal 8,4 % × 432 000 = 36 288
        //   Tranche T2 = 1 000 000 − 432 000 = 568 000
        //   T2 salarié 2,4 % × 568 000 = 13 632 · T2 patronal 3,6 % × 568 000 = 20 448
        //   CSS famille 7 % × min(1M, 63 k) = 4 410 (CIPRES #2473) · CSS AT
        //   1 % × 63 k = 630 · CFCE 3 % = 30 000 (plafonds #1913)
        //   → salarié 37 824 · patronal 91 776
        $charges = $this->sn()->calculateSocialCharges(1000000.0);

        self::assertSame(SnPayrollFixtures::socialCharges(1000000.0)['employee'], $charges['employee']);
        self::assertSame(SnPayrollFixtures::socialCharges(1000000.0)['employer'], $charges['employer']);
    }

    public function test_professional_expenses_30_percent(): void
    {
        $abatement = $this->sn()->professionalExpensesDeduction();

        // #1912 : 30 % du brut plafonné à 900 000/an = 75 000/mois (CGI art. 168).
        self::assertSame(30.0, $abatement['rate']);
        self::assertSame(75000.0, $abatement['cap']);

        self::assertSame(90000.0, 300000.0 * 0.30);
    }

    /**
     * Issue #1934 — mécanisme légal « max(IR, TRIMF) » : le salarié SN paie
     * le plus élevé des deux (le TRIMF est un minimum représentatif de
     * l'impôt, docs/payroll/SN_COMPLIANCE.md §3). La règle SN override
     * combineMinimumFiscalTax() ; les autres pays restent additifs (CI :
     * IR + CN).
     */
    public function test_combine_minimum_fiscal_tax_max_not_cumulative(): void
    {
        // Sous le seuil TRIMF : TRIMF gagne (brut 100 000 → IR 2 380 < TRIMF 5 400).
        self::assertSame(5400.0, $this->sn()->combineMinimumFiscalTax(2380.0, 5400.0));
        // Au-dessus : IR gagne (brut 250 000 → IR 25 300 > TRIMF 9 000).
        self::assertSame(25300.0, $this->sn()->combineMinimumFiscalTax(25300.0, 9000.0));
        // Égalité : IR conservé (>=).
        self::assertSame(2700.0, $this->sn()->combineMinimumFiscalTax(2700.0, 2700.0));
    }

    /**
     * Issue #1934 — golden : net du bulletin SN via le noyau commun
     * (computeNetBreakdown) avec le mécanisme max(IR, TRIMF).
     * Cas « sous le seuil » (TRIMF > IR) et « au-dessus du seuil » (IR > TRIMF).
     */
    public function test_golden_sn_net_with_max_ir_trimf(): void
    {
        $calculator = new PayrollCalculator;

        // #1912 (TRIMF révisé) — Brut 60 000 : IPRES 3 360 ; IR = 0 (annuel
        // < 630 000) ; TRIMF 900 (≤ 75 000) → déductions = 3 360 + 900 = 4 260
        // → net 55 740.
        $b60 = $calculator->computeNetBreakdown(60000.0, $this->sn());
        self::assertSame(4260.0, $b60['base_deductions']);
        self::assertSame(55740.0, $b60['net_salary']);

        // Brut 100 000 — IPRES 5 600 ; IR 2 380 > TRIMF 1 800 (≤ 200 000) →
        // déductions = 5 600 + 2 380 = 7 980 → net 92 020.
        $b100 = $calculator->computeNetBreakdown(100000.0, $this->sn());
        self::assertSame(7980.0, $b100['base_deductions']);
        self::assertSame(92020.0, $b100['net_salary']);

        // Brut 250 000 — IPRES 14 000 ; IR 25 300 > TRIMF 9 000 →
        // déductions = 14 000 + 25 300 = 39 300 → net 210 700.
        $b250 = $calculator->computeNetBreakdown(250000.0, $this->sn());
        self::assertSame(39300.0, $b250['base_deductions']);
        self::assertSame(210700.0, $b250['net_salary']);
    }

    /**
     * Issue #1934 — les autres pays restent ADDITIFS (pas de changement de
     * comportement hors SN) : CI → IR + CN.
     */
    public function test_other_countries_remain_additive(): void
    {
        $ci = new CedeaoPayrollRules('CI');

        self::assertSame(4000.0, $ci->combineMinimumFiscalTax(3000.0, 1000.0));
    }
}
