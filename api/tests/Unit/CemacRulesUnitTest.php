<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use PHPUnit\Framework\TestCase;

/**
 * Issue #1821 — Cameroun (CM) : passage de placeholder → pilot.
 *
 * Barèmes IRPP CGI 2024 (art. 68), CNPS (vieillesse 4,2/4,2, famille 7,0,
 * AT 2,0, plafond 750 000 XAF), abattement frais pro 30 % (plaf. 350 000/mois),
 * centimes additionnels ×1,10, préavis art. 34 (15/30/60/90 j).
 *
 * Les 5 autres membres CEMAC (CF/TD/CG/GA/GQ) ne sont PAS affectés
 * (placeholder conservé). Référentiel : docs/payroll/CM_COMPLIANCE.md.
 *
 * Tests purs (pas de DB) : les règles retombent sur les barèmes codés en dur.
 */
class CemacRulesUnitTest extends TestCase
{
    private function cm(): CemacPayrollRules
    {
        return (new CemacPayrollRules)->forMemberCountry('CM');
    }

    public function test_cm_irpp_4_tranches_not_generic(): void
    {
        $slabs = $this->cm()->taxSlabs();

        // 4 tranches CM (CGI 2024) au lieu des 5 génériques du placeholder.
        $this->assertCount(4, $slabs);
        $this->assertSame(10, $slabs[0]['rate']);
        $this->assertSame(2000000, $slabs[0]['max']);
        $this->assertSame(35, $slabs[3]['rate']);
        $this->assertNull($slabs[3]['max']);
    }

    public function test_cm_cnps_cap_at_750k(): void
    {
        $charges = $this->cm()->calculateSocialCharges(2000000.0);

        // Vieillesse/famille plafonnées à 750 000 ; AT (2 %) non plafonné :
        //   salariale : 750 000 × 4,2 % = 31 500
        //   patronale : 750 000 × 11,2 % + 2 000 000 × 2 % = 84 000 + 40 000
        $this->assertSame(31500.0, $charges['employee']);
        $this->assertSame(124000.0, $charges['employer']);
    }

    public function test_cm_centimes_additionnels_applied(): void
    {
        // Base mensuelle après CNPS = 500 000 XAF :
        //   abattement = min(500 000 × 30 %, 350 000) = 150 000
        //   assiette mensuelle = 350 000 → annuelle 4 200 000
        //   IRPP annuel : 2 000 000×10 % + 1 000 000×15 % + 1 200 000×25 %
        //               = 200 000 + 150 000 + 300 000 = 650 000
        //   IRPP mensuel = 54 166,67 × 1,10 (centimes) = 59 583,33
        $this->assertSame(59583.33, $this->cm()->calculateIncomeTax(500000.0));
    }

    public function test_cm_abatement_30_percent_pro_expenses(): void
    {
        $rules = $this->cm();

        $this->assertSame(
            ['rate' => 30.0, 'cap' => 350000.0],
            $rules->professionalExpensesDeduction()
        );

        // L'abattement réduit bien l'assiette : sans abattement l'IRPP serait
        // supérieur. 500 000 brut mensuel → abattement 150 000 (voir
        // test_cm_centimes_additionnels_applied pour le calcul complet).
        $this->assertSame(59583.33, $rules->calculateIncomeTax(500000.0));
    }

    public function test_cm_notice_period_by_seniority(): void
    {
        $rules = $this->cm();

        $this->assertSame(15.0, $rules->noticePeriodDays(0.4));  // < 6 mois
        $this->assertSame(30.0, $rules->noticePeriodDays(3.0));  // 6 mois – 5 ans
        $this->assertSame(60.0, $rules->noticePeriodDays(7.0));  // 5 – 10 ans
        $this->assertSame(90.0, $rules->noticePeriodDays(12.0)); // > 10 ans
    }

    public function test_other_cemac_members_unaffected(): void
    {
        // TD/CF/GQ restent placeholder non plafonnés (#1821/#1824).
        foreach (['TD', 'CF', 'GQ'] as $memberCode) {
            $rules = (new CemacPayrollRules)->forMemberCountry($memberCode);

            $this->assertSame('placeholder', $rules->confidenceLevel(), $memberCode);
            $this->assertCount(5, $rules->taxSlabs(), $memberCode);
            $this->assertSame(0.0, $rules->noticePeriodDays(3.0), $memberCode);

            $charges = $rules->calculateSocialCharges(2000000.0);
            $this->assertSame(84000.0, $charges['employee'], $memberCode);
            $this->assertSame(324000.0, $charges['employer'], $memberCode);
        }
    }

    public function test_ga_is_pilot(): void
    {
        // GA (#1824) passé pilot : barème IRPP à 8 tranches, préavis
        // calendaire par ancienneté, CNPS non plafonnée — aligné sur les
        // golden tests et docs/payroll/GA_COMPLIANCE.md.
        $ga = (new CemacPayrollRules)->forMemberCountry('GA');

        $this->assertCount(8, $ga->taxSlabs());
        $this->assertSame(['rate' => 0.0, 'cap' => null], $ga->professionalExpensesDeduction());
        $this->assertSame(30.0, $ga->noticePeriodDays(3.0));
        $this->assertSame('pilot', $ga->confidenceLevel());

        // CNPS GA (2 000 000 × 2,5 % salariale / × 16,0 % patronale).
        $charges = $ga->calculateSocialCharges(2000000.0);
        $this->assertSame(50000.0, $charges['employee']);
        $this->assertSame(320000.0, $charges['employer']);
    }

    public function test_cm_confidence_level_is_pilot(): void
    {
        $this->assertSame('pilot', $this->cm()->confidenceLevel());
        $this->assertSame(41875.0, $this->cm()->minimumWage());
    }
}
