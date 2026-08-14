<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use PHPUnit\Framework\TestCase;

/**
 * Issue #1821 — CEMAC/CM : CemacPayrollRules passe de « placeholder » à
 * « pilot » pour le Cameroun (IRPP CGI 2024 art. 68, CNPS 2024, préavis loi
 * 92/007). Volontairement SANS base de données : les règles retombent sur
 * les barèmes par défaut quand tax_slabs/social_contributions sont vides
 * (méthodologie golden F-03/F-13). Référence légale :
 * docs/payroll/CM_COMPLIANCE.md.
 */
class CemacRulesUnitTest extends TestCase
{
    private function cm(): CemacPayrollRules
    {
        return new CemacPayrollRules('CM');
    }

    public function test_cm_irpp_4_tranches_not_generic(): void
    {
        $slabs = $this->cm()->taxSlabs();

        // CM : 4 tranches annuelles (CGI 2024 art. 68) — le placeholder
        // générique CEMAC en compte 5 (0-500k, 500k-1M, 1M-2.5M, 2.5M-5M, >5M).
        self::assertCount(4, $slabs, 'CM doit utiliser les 4 tranches IRPP du CGI 2024, pas le placeholder générique');

        self::assertSame([0, 2000000, 10], [$slabs[0]['min'], $slabs[0]['max'], $slabs[0]['rate']]);
        self::assertSame([2000001, 3000000, 15], [$slabs[1]['min'], $slabs[1]['max'], $slabs[1]['rate']]);
        self::assertSame([3000001, 5000000, 25], [$slabs[2]['min'], $slabs[2]['max'], $slabs[2]['rate']]);
        self::assertSame([5000001, null, 35], [$slabs[3]['min'], $slabs[3]['max'], $slabs[3]['rate']]);
    }

    public function test_cm_cnps_cap_at_750k(): void
    {
        // Calcul manuel (docs/payroll/CM_COMPLIANCE.md §3) — brut 1 000 000 :
        //   Vieillesse salariale 4,2 % × min(1M, 750k) = 31 500
        //   Vieillesse patronale 4,2 % × 750k          = 31 500
        //   Famille patronale 7,0 % × 750k             = 52 500
        //   AT patronale 2,0 % × 1M (non plafonné)     = 20 000
        //   → salarié 31 500 · patronal 104 000
        $charges = $this->cm()->calculateSocialCharges(1000000.0);

        self::assertSame(31500.0, $charges['employee']);
        self::assertSame(104000.0, $charges['employer']);
    }

    public function test_cm_centimes_additionnels_applied(): void
    {
        // Calcul manuel (docs/payroll/CM_COMPLIANCE.md §2) :
        //   Assiette mensuelle 131 600 → annuelle 1 579 200 → tranche 10 %
        //   = 157 920 → mensuel 13 160 → centimes ×1,10 = 14 476
        self::assertSame(14476.0, $this->cm()->calculateIncomeTax(131600.0));
    }

    public function test_cm_abatement_30_percent_pro_expenses(): void
    {
        $abatement = $this->cm()->professionalExpensesDeduction();

        self::assertSame(30.0, $abatement['rate']);
        self::assertSame(350000.0, $abatement['cap']);

        // 30 % de 200 000 = 60 000 < plafond 350 000 → 60 000.
        self::assertSame(60000.0, min(200000.0 * 0.30, 350000.0));
        // 30 % de 1 500 000 = 450 000 > plafond → 350 000 (plafonné).
        self::assertSame(350000.0, min(1500000.0 * 0.30, 350000.0));
    }

    public function test_cm_notice_period_by_seniority(): void
    {
        // Code du travail CM (loi 92/007, art. 34) — docs/payroll/CM_COMPLIANCE.md §8 :
        //   < 6 mois : 15 j ; 6 mois–5 ans : 30 j ; 5–10 ans : 60 j ; > 10 ans : 90 j.
        $rules = $this->cm();

        self::assertSame(15.0, $rules->noticePeriodDays(0.25));
        self::assertSame(30.0, $rules->noticePeriodDays(0.5));   // borne : 6 mois
        self::assertSame(30.0, $rules->noticePeriodDays(4.9));
        self::assertSame(60.0, $rules->noticePeriodDays(5.0));   // borne : 5 ans
        self::assertSame(60.0, $rules->noticePeriodDays(9.9));
        self::assertSame(90.0, $rules->noticePeriodDays(10.0));  // borne : 10 ans
        self::assertSame(90.0, $rules->noticePeriodDays(15.0));
    }

    public function test_other_cemac_members_unaffected(): void
    {
        // Les membres CF/TD/CG/GA/GQ ne doivent PAS hériter des règles CM.
        foreach (['CF', 'TD', 'CG', 'GA', 'GQ'] as $memberCode) {
            $rules = (new CemacPayrollRules)->forMemberCountry($memberCode);

            self::assertSame('placeholder', $rules->confidenceLevel(), "{$memberCode} doit rester placeholder");
            self::assertCount(5, $rules->taxSlabs(), "{$memberCode} doit garder le barème placeholder générique");
            self::assertSame(0.0, $rules->noticePeriodDays(7.0), "{$memberCode} n'a pas de préavis légal CM");

            // Codes CNPS génériques, pas les codes CM.
            $codes = array_column($rules->socialContributions(), 'code');
            self::assertNotContains('CNPS_CM_VIE_EMP', $codes, "{$memberCode} ne doit pas exposer les codes CNPS CM");
        }

        // GA conserve le comportement placeholder (4,2 % / 16,2 % non plafonnés).
        $gaCharges = (new CemacPayrollRules)->forMemberCountry('GA')->calculateSocialCharges(1000.0);
        self::assertSame(42.0, $gaCharges['employee']);
        self::assertSame(162.0, $gaCharges['employer']);

        // CM au même brut diverge (4 cotisations dont AT non plafonné).
        $cmCharges = $this->cm()->calculateSocialCharges(1000.0);
        self::assertSame(42.0, $cmCharges['employee']);
        self::assertSame(132.0, $cmCharges['employer']);
    }
}
