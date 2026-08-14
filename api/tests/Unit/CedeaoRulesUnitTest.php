<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use PHPUnit\Framework\TestCase;

/**
 * #1825 — Côte d'Ivoire (CI) : CedeaoPayrollRules de "placeholder" → "pilot".
 *
 * Règles implémentées (référence : docs/payroll/CI_COMPLIANCE.md) :
 *   - ITSAS (CGI CI art. 116-120) : 5 tranches annuelles, assiette =
 *     brut − CNSS salariale − abattement frais pro 20 % (non plafonné)
 *   - CN (Contribution Nationale) : 1,5 % sur la part du brut mensuel
 *     > 50 000 XOF — calculée séparément (calculateBracketTax) et sommée
 *     avec l'ITSAS sur le bulletin
 *   - CNSS : retraite 3,2 % salarié + 4,5 % patronal, famille 5,75 %
 *     patronal (plafond 1 647 315 XOF/mois), AT 2,0 % patronal non plafonné
 *   - 13ème mois obligatoire (conventions de branche OHADA-CI)
 *   - Préavis Code du travail CI art. 18 (palier ancienneté, pilote)
 *   - Les autres membres UEMOA (ML/BF/BJ/TG/NE) restent inchangés
 *     (placeholder).
 *
 * Tests purs (pas de DB) : les règles retombent sur les barèmes par défaut
 * quand `tax_slabs`/`social_contributions` sont vides.
 */
class CedeaoRulesUnitTest extends TestCase
{
    private function rules(): CedeaoPayrollRules
    {
        return (new CedeaoPayrollRules)->forMemberCountry('CI');
    }

    public function test_ci_confidence_level_is_pilot(): void
    {
        $this->assertSame('pilot', $this->rules()->confidenceLevel());
        $this->assertSame('CI', $this->rules()->countryCode());
    }

    public function test_ci_itsas_and_cn_calculated_separately(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1) — brut mensuel 200 000 XOF :
        //   CNSS salariale 3,2 % = 6 400 → assiette transmise par le moteur
        //   = 200 000 − 6 400 = 193 600.
        //   Abattement 20 % = 38 720 → base ITSAS = 154 880/mois
        //   → annuel 1 858 560 → tranche 2 % sur (1 858 560 − 600 000)
        //   = 1 258 560 × 2 % = 25 171,20 → ITSAS mensuel = 2 097,60.
        //   CN = (200 000 − 50 000) × 1,5 % = 2 250,00.
        //   Impôt total mensuel = 2 097,60 + 2 250,00 = 4 347,60.
        $itsas = $this->rules()->calculateIncomeTax(193600.0);
        $cn = $this->rules()->calculateBracketTax(200000.0);

        $this->assertSame(2097.6, $itsas);
        $this->assertSame(2250.0, $cn);
        $this->assertSame(4347.6, round($itsas + $cn, 2));
    }

    public function test_ci_abatement_20_percent(): void
    {
        // Sans abattement, l'ITSAS sur 193 600/mois (annuel 2 323 200)
        // serait 2 872,00 ; avec abattement 20 % (annuel 1 858 560) il
        // tombe à 2 097,60 (CI_COMPLIANCE.md §1).
        $this->assertSame(
            ['rate' => 20.0, 'cap' => null],
            $this->rules()->professionalExpensesDeduction()
        );
        $this->assertSame(2097.6, $this->rules()->calculateIncomeTax(193600.0));

        // Haut salaire : abattement 20 % appliqué sans plafonnement.
        // Brut 1 000 000 → CNSS 32 000 → assiette 968 000 → base 774 400
        // → annuel 9 292 800 → tranches 2 %/21 %/24,5 % → 1 709 736
        // → mensuel 142 478,00 (CI_COMPLIANCE.md §1, cas haut salaire).
        $this->assertSame(142478.0, $this->rules()->calculateIncomeTax(968000.0));
    }

    public function test_ci_cn_below_threshold_is_zero(): void
    {
        // CN = max(0, (brut − 50 000)) × 1,5 % → brut ≤ 50 000 → 0.
        $this->assertSame(0.0, $this->rules()->calculateBracketTax(50000.0));
        $this->assertSame(0.0, $this->rules()->calculateBracketTax(49999.99));
        // Brut 75 000 → CN = 25 000 × 1,5 % = 375,00.
        $this->assertSame(375.0, $this->rules()->calculateBracketTax(75000.0));
    }

    public function test_ci_cnss_cap_at_1647315(): void
    {
        // Brut 2 000 000 XOF > plafond 1 647 315 → retraite/famille assises
        // sur le plafond, AT non plafonné (CI_COMPLIANCE.md §3) :
        //   salariale : 1 647 315 × 3,2 % = 52 714,08
        //   patronale : 1 647 315 × 4,5 % = 74 129,18
        //             + 1 647 315 × 5,75 % = 94 720,61
        //             + 2 000 000 × 2,0 % = 40 000,00
        //             = 208 849,79
        $charges = $this->rules()->calculateSocialCharges(2000000.0);

        $this->assertSame(52714.08, $charges['employee']);
        $this->assertSame(208849.79, $charges['employer']);
    }

    public function test_ci_cnss_below_cap_uses_full_gross(): void
    {
        // Brut 200 000 < plafond → assiette pleine (CI_COMPLIANCE.md §3) :
        //   salariale : 200 000 × 3,2 % = 6 400,00
        //   patronale : 200 000 × (4,5 % + 5,75 % + 2,0 %) = 24 500,00
        $charges = $this->rules()->calculateSocialCharges(200000.0);

        $this->assertSame(6400.0, $charges['employee']);
        $this->assertSame(24500.0, $charges['employer']);
    }

    public function test_ci_social_contributions_list_uses_legal_codes(): void
    {
        $contributions = $this->rules()->socialContributions();

        $this->assertCount(4, $contributions);
        $this->assertSame(
            ['CNSS_CI_RET_EMP', 'CNSS_CI_RET_PAT', 'CNSS_CI_FAM_PAT', 'CNSS_CI_AT_PAT'],
            array_column($contributions, 'code')
        );
        $this->assertSame([3.2, 4.5, 5.75, 2.0], array_column($contributions, 'rate'));
        $this->assertSame([1647315.0, 1647315.0, 1647315.0, null], array_column($contributions, 'cap'));
    }

    public function test_ci_thirteenth_month_mandatory(): void
    {
        $this->assertTrue($this->rules()->thirteenthMonthMandatory());
        $this->assertFalse((new CedeaoPayrollRules)->forMemberCountry('BF')->thirteenthMonthMandatory());
    }

    public function test_ci_notice_period_days(): void
    {
        // CI_COMPLIANCE.md §6 — palier ancienneté (approximation pilote,
        // matrice complète ouvriers/employés/cadres documentée) :
        //   < 5 ans : 30 j · 5–10 ans : 60 j · > 10 ans : 90 j.
        $this->assertSame(30.0, $this->rules()->noticePeriodDays(3.0));
        $this->assertSame(60.0, $this->rules()->noticePeriodDays(7.0));
        $this->assertSame(90.0, $this->rules()->noticePeriodDays(12.0));
    }

    public function test_ci_overtime_tiers(): void
    {
        // Code du travail CI art. 21 : +15 % (8 premières h HS),
        // +35 % (9-14 h HS), +50 % au-delà (nuit/dimanche).
        $tiers = $this->rules()->overtimeRateTiers();

        $this->assertSame(
            [
                ['up_to_hours' => 8.0, 'multiplier' => 1.15],
                ['up_to_hours' => 14.0, 'multiplier' => 1.35],
                ['up_to_hours' => null, 'multiplier' => 1.50],
            ],
            $tiers
        );
    }

    public function test_ci_flat_tax_label_is_contribution_nationale(): void
    {
        $this->assertSame('Contribution Nationale (CN)', $this->rules()->flatPayrollTaxLabel());
        // Les autres pays gardent le libellé générique (ex. TRIMF SN).
        $this->assertSame('Taxe de minimum fiscal', (new CedeaoPayrollRules)->forMemberCountry('BF')->flatPayrollTaxLabel());
    }

    public function test_other_uemoa_members_unaffected(): void
    {
        foreach (['ML', 'BF', 'BJ', 'TG', 'NE'] as $memberCode) {
            $rules = (new CedeaoPayrollRules)->forMemberCountry($memberCode);

            // Placeholder intact : pas de barème légal, pas de CN, pas de
            // 13ème mois, pas d'abattement frais pro.
            $this->assertSame('placeholder', $rules->confidenceLevel(), $memberCode);
            $this->assertStringContainsString('placeholder', $rules->publicHolidaysSource(), $memberCode);
            $this->assertSame(0.0, $rules->calculateBracketTax(200000.0), $memberCode);
            $this->assertFalse($rules->thirteenthMonthMandatory(), $memberCode);
            $this->assertSame(['rate' => 0.0, 'cap' => null], $rules->professionalExpensesDeduction(), $memberCode);
            $this->assertSame(0.0, $rules->calculateIncomeTax(600000 / 12), $memberCode);

            // Charges placeholder non plafonnées inchangées (3,6 % / 16,4 %).
            $charges = $rules->calculateSocialCharges(1000.0);
            $this->assertSame(36.0, $charges['employee'], $memberCode);
            $this->assertSame(164.0, $charges['employer'], $memberCode);
        }
    }
}
