<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use PHPUnit\Framework\TestCase;

/**
 * #1825 — Côte d'Ivoire (CI) : CedeaoPayrollRules de "placeholder" → "pilot".
 *
 * Règles implémentées (référence : docs/payroll/CI_COMPLIANCE.md) :
 *   - ITS unifié 2024 (ordonnance 2023-718/719, CGI art. 119 bis) : 6
 *     tranches MENSUELLES sur le brut (0/16/21/24/28/32 %) — réforme qui
 *     supprime l'ancien ITSAS annuel (0/2/21/24,5/29 %) et la CN 1,5 %
 *     (fusionnés dans l'ITS unique, #1918)
 *   - CNSS : retraite 3,2 % salarié + 4,5 % patronal, famille 5,75 %
 *     patronal (plafond retraite 1 647 315 XOF/mois), famille/AT plafonnées
 *     séparément à 70 000 (#1913, guide CNPS)
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

    public function test_ci_its_2024_unique_on_gross(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1 — ITS 2024, #1918) — base = brut
        // mensuel 200 000 XOF (la CNSS salariale n'est plus déduite de la
        // base ITS depuis la réforme) :
        //   ITS = 75 001–200 000 × 16 % = 125 000 × 16 % = 20 000,00.
        //   CN abolie → calculateBracketTax = 0,00.
        //   Impôt total mensuel = 20 000,00.
        $its = $this->rules()->calculateIncomeTax(200000.0);
        $cn = $this->rules()->calculateBracketTax(200000.0);

        $this->assertSame(20000.0, $its);
        $this->assertSame(0.0, $cn);
        $this->assertSame(20000.0, round($its + $cn, 2));
    }

    public function test_ci_abatement_supprime_2024(): void
    {
        // ITS 2024 (#1918) : plus d'abattement frais pro — l'ITS s'applique
        // sur le brut (art. 119 bis).
        $this->assertSame(
            ['rate' => 0.0, 'cap' => null],
            $this->rules()->professionalExpensesDeduction()
        );
        // Brut 193 600 → 75 001–193 600 × 16 % = 118 600 × 16 % = 18 976,00.
        $this->assertSame(18976.0, $this->rules()->calculateIncomeTax(193600.0));

        // Haut salaire : brut 968 000 → 26 400 (75 001–240 000 × 16 %)
        // + 117 600 (240 001–800 000 × 21 %) + 168 000 × 24 %
        // (800 001–968 000) = 40 320 → 184 320,00.
        $this->assertSame(184320.0, $this->rules()->calculateIncomeTax(968000.0));
    }

    public function test_ci_cn_aboli_depuis_2024(): void
    {
        // CN (1,5 %) supprimée par la réforme 2024 (fusionnée dans l'ITS)
        // → calculateBracketTax = 0 quel que soit le brut (#1918).
        $this->assertSame(0.0, $this->rules()->calculateBracketTax(50000.0));
        $this->assertSame(0.0, $this->rules()->calculateBracketTax(49999.99));
        $this->assertSame(0.0, $this->rules()->calculateBracketTax(75000.0));
        $this->assertSame(0.0, $this->rules()->calculateBracketTax(5000000.0));
    }

    public function test_ci_cnss_cap_at_1647315(): void
    {
        // Brut 2 000 000 XOF > plafond retraite 1 647 315 ; famille et AT
        // plafonnées séparément à 70 000 (#1913, guide CNPS) :
        //   salariale : 1 647 315 × 3,2 % = 52 714,08
        //   patronale : 1 647 315 × 4,5 % = 74 129,18
        //             + 70 000 × 5,75 % = 4 025,00
        //             + 70 000 × 2,0 % = 1 400,00
        //             = 79 554,18
        $charges = $this->rules()->calculateSocialCharges(2000000.0);

        $this->assertSame(52714.08, $charges['employee']);
        $this->assertSame(79554.18, $charges['employer']);
    }

    public function test_ci_cnss_below_cap_uses_full_gross(): void
    {
        // Brut 200 000 — retraite assise sur le brut (< 1 647 315), famille/AT
        // plafonnées à 70 000 (#1913) :
        //   salariale : 200 000 × 3,2 % = 6 400,00
        //   patronale : 200 000 × 4,5 % = 9 000,00
        //             + 70 000 × 5,75 % = 4 025,00 + 70 000 × 2,0 % = 1 400,00
        //             = 14 425,00
        $charges = $this->rules()->calculateSocialCharges(200000.0);

        $this->assertSame(6400.0, $charges['employee']);
        $this->assertSame(14425.0, $charges['employer']);
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
        // #1913 : plafonds par branche (retraite 1 647 315 ; famille/AT 70 000).
        $this->assertSame([1647315.0, 1647315.0, 70000.0, 70000.0], array_column($contributions, 'cap'));
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
        $this->assertSame('Taxe de minimum fiscal', $this->rules()->flatPayrollTaxLabel());
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
