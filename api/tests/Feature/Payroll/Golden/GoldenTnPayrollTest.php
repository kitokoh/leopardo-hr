<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\TunisiaPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests Tunisie (TN) — issue #5249, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/TN_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot) — audit légal 2026 (issue #5249) :
 *   CNSS régime non agricole 9,18 % sal. / 16,57 % pat. (sans plafond général)
 *   + fonds perte d'emploi (LF 2025 art. 17) 0,50 % sal. / 0,50 % pat.
 *   → salarié 9,68 % · employeur 17,07 % (+ ASSP patronale 1,00 % pilot →
 *   total employeur 18,07 %).
 *   IRPP = progressif ANNUEL (CGI TN art. 36 — LF 2025 : 8 tranches, 0 % à
 *   40 %) / 12, assiette = brut − cotisations salariales − abattement
 *   art. 39 (10 %, borné [1 000 ; 1 500 TND/an]).
 */
class GoldenTnPayrollTest extends TestCase
{
    private function rules(): TunisiaPayrollRules
    {
        return new TunisiaPayrollRules;
    }

    /**
     * Cas complets brut → cotisations → IRPP → net, calculés à la main.
     *
     * @return iterable<string, array{0: float, 1: float, 2: float, 3: float, 4: float}>
     */
    public static function goldenFullPathProvider(): iterable
    {
        yield 'SMIG 2026 (554,736 — 48 h)' => [554.736, 53.69, 100.24, 0.16, 500.89];
        yield 'Ouvrier (1 000)' => [1000.0, 96.80, 180.70, 59.43, 843.77];
        yield 'Cadre moyen (2 000)' => [2000.0, 193.60, 361.40, 275.25, 1531.15];
        yield 'Cadre supérieur (5 000)' => [5000.0, 484.00, 903.50, 1181.08, 3334.92];
        yield 'Haut salaire (10 000)' => [10000.0, 968.00, 1807.00, 2958.63, 6073.37];
    }

    #[DataProvider('goldenFullPathProvider')]
    public function test_golden_tn_full_path(
        float $gross,
        float $expectedEmployee,
        float $expectedEmployer,
        float $expectedTax,
        float $expectedNet,
    ): void {
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges($gross);
        $this->assertSame($expectedEmployee, $charges['employee']);
        $this->assertSame($expectedEmployer, $charges['employer']);

        $tax = $rules->calculateIncomeTax($gross - $charges['employee']);
        $this->assertSame($expectedTax, $tax);

        $this->assertSame($expectedNet, round($gross - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tn_smig_2026_detail(): void
    {
        // Calcul manuel, brut = SMIG 2026 554,736 TND (décret 2026-67, 48 h) :
        //   CNSS salariale = 554,736 × 9,18 % = 50,92 (régime non agricole)
        //   Perte d'emploi salariée = 554,736 × 0,50 % = 2,77
        //   → total salarié 53,69 (somme des lignes arrondies — constitution
        //   §III) · employeur 100,24 (CNSS 91,92 + PLE 2,77 + ASSP 5,55)
        //   Assiette IRPP = 554,736 − 53,69 = 501,05 → annuel 6 012,55
        //   Abattement art. 39 : 10 % = 601,26 → plancher 1 000 → 5 012,55
        //   → tranche 15 % (0 % jusqu'à 5 000) : 12,55 × 15 % = 1,88/an
        //   → IRPP mensuel 0,16 · net = 554,736 − 53,69 − 0,16 = 500,89
        $rules = $this->rules();

        $this->assertSame(554.736, $rules->minimumWage());

        $charges = $rules->calculateSocialCharges(554.736);
        $this->assertSame(53.69, $charges['employee']);
        $this->assertSame(100.24, $charges['employer']);

        $this->assertSame(0.16, $rules->calculateIncomeTax(554.736 - $charges['employee']));
        $this->assertSame(500.89, round(554.736 - $charges['employee'] - 0.16, 2));
    }

    public function test_golden_tn_cadre_moyen_2000_detail(): void
    {
        // Calcul manuel, brut 2 000 TND :
        //   CNSS = 183,60 · PLE = 10,00 → salarié 193,60
        //   Assiette IRPP = 1 806,40 → annuel 21 676,80
        //   Abattement art. 39 : 10 % = 2 167,68 → plafonné 1 500
        //   → imposable 20 176,80 : 5 000 × 0 % + 5 000 × 15 % (750)
        //   + 10 000 × 25 % (2 500) + 176,80 × 30 % (53,04) = 3 303,04/an
        //   → IRPP mensuel 275,25 · net = 2 000 − 193,60 − 275,25 = 1 531,15
        $charges = $this->rules()->calculateSocialCharges(2000.0);
        $this->assertSame(193.60, $charges['employee']);
        $this->assertSame(361.40, $charges['employer']); // 331,40 CNSS + 10,00 PLE + 20,00 ASSP

        $tax = $this->rules()->calculateIncomeTax(2000.0 - $charges['employee']);
        $this->assertSame(275.25, $tax);
        $this->assertSame(1531.15, round(2000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tn_haut_salaire_10000_detail(): void
    {
        // Calcul manuel, brut 10 000 TND :
        //   salarié = 968,00 (CNSS 918,00 + PLE 50,00)
        //   Assiette IRPP = 9 032,00 → annuel 108 384,00 → abattement plafonné
        //   1 500 → imposable 106 884,00 : 0 + 750 + 2 500 + 3 000 + 3 300
        //   + 3 600 + 20 000 × 38 % (7 600) + 36 884 × 40 % (14 753,60)
        //   = 35 503,60/an → IRPP mensuel 2 958,63
        //   net = 10 000 − 968,00 − 2 958,63 = 6 073,37
        $charges = $this->rules()->calculateSocialCharges(10000.0);
        $this->assertSame(968.00, $charges['employee']);
        $this->assertSame(1807.00, $charges['employer']); // 1 657 + 50 + 100

        $tax = $this->rules()->calculateIncomeTax(10000.0 - $charges['employee']);
        $this->assertSame(2958.63, $tax);
        $this->assertSame(6073.37, round(10000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_tn_legal_reference_slabs_2026(): void
    {
        // CGI TN art. 36 (LF 2025, loi n° 2024-48) — barème IRPP 2026 : le
        // référentiel légal doit être EXACTEMENT la table à 8 tranches.
        $this->assertSame([
            ['min' => 0, 'max' => 5000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 5001, 'max' => 10000, 'rate' => 15, 'fixed_deduction' => 0],
            ['min' => 10001, 'max' => 20000, 'rate' => 25, 'fixed_deduction' => 0],
            ['min' => 20001, 'max' => 30000, 'rate' => 30, 'fixed_deduction' => 0],
            ['min' => 30001, 'max' => 40000, 'rate' => 33, 'fixed_deduction' => 0],
            ['min' => 40001, 'max' => 50000, 'rate' => 36, 'fixed_deduction' => 0],
            ['min' => 50001, 'max' => 70000, 'rate' => 38, 'fixed_deduction' => 0],
            ['min' => 70001, 'max' => null, 'rate' => 40, 'fixed_deduction' => 0],
        ], $this->rules()->legalReferenceTaxSlabs());
    }

    public function test_golden_tn_irpp_matches_worked_examples(): void
    {
        // Exemples publiés SmartPaie 2026 (barème LF 2025) — vérifiés contre
        // le calcul progressif : 25 000 → 4 750 · 35 000 → 7 900 ·
        // 60 000 → 16 950. Au-delà de 15 000 d'assiette annuelle l'abattement
        // art. 39 est plafonné à 1 500 → brut annuel = assiette + 1 500.
        $rules = $this->rules();

        // 26 500/an − abattement 1 500 = 25 000 → 4 750/an → 395,83/mois
        $this->assertSame(395.83, $rules->calculateIncomeTax(26500.0 / 12));
        // 36 500/an − 1 500 = 35 000 → 7 900/an → 658,33/mois
        $this->assertSame(658.33, $rules->calculateIncomeTax(36500.0 / 12));
        // 61 500/an − 1 500 = 60 000 → 16 950/an → 1 412,50/mois
        $this->assertSame(1412.50, $rules->calculateIncomeTax(61500.0 / 12));
    }

    public function test_golden_tn_abatement_art39(): void
    {
        // CGI TN art. 39 : abattement 10 % du revenu annuel, borné
        // [1 000 ; 1 500 TND/an], appliqué AVANT le barème.
        $rules = $this->rules();

        // 8 000 × 10 % = 800 → plancher 1 000 → assiette 7 000
        $this->assertSame(7000.0, $rules->applyAnnualAbatement(8000.0));
        // 12 000 × 10 % = 1 200 → assiette 10 800
        $this->assertSame(10800.0, $rules->applyAnnualAbatement(12000.0));
        // 20 000 × 10 % = 2 000 → plafond 1 500 → assiette 18 500
        $this->assertSame(18500.0, $rules->applyAnnualAbatement(20000.0));
    }

    public function test_golden_tn_social_contributions_schedule(): void
    {
        // Audit 2026 : CNSS 9,18/16,57 % (sans plafond général) + perte
        // d'emploi LF 2025 0,50/0,50 % + ASSP patronale 1,00 % (pilot —
        // secteur 0,4-4 %).
        $this->assertSame([
            ['name' => 'CNSS Salariale (régime non agricole)', 'code' => 'CNSS_TN_EMP', 'type' => 'employee', 'rate' => 9.18, 'cap' => null],
            ['name' => 'CNSS Patronale (régime non agricole)', 'code' => 'CNSS_TN_PAT', 'type' => 'employer', 'rate' => 16.57, 'cap' => null],
            ['name' => 'Fonds perte d\'emploi salarié', 'code' => 'PLE_TN_EMP', 'type' => 'employee', 'rate' => 0.50, 'cap' => null],
            ['name' => 'Fonds perte d\'emploi patronal', 'code' => 'PLE_TN_PAT', 'type' => 'employer', 'rate' => 0.50, 'cap' => null],
            ['name' => 'ASSP — accidents du travail et maladies professionnelles (patronale)', 'code' => 'ASSP_TN_PAT', 'type' => 'employer', 'rate' => 1.00, 'cap' => null],
        ], $this->rules()->socialContributions());
    }

    public function test_golden_tn_country_parameters(): void
    {
        $rules = $this->rules();

        $this->assertSame('TN', $rules->countryCode());
        $this->assertSame('TND', $rules->currency());
        $this->assertSame('Africa/Tunis', $rules->timezone());
        $this->assertSame('fr', $rules->language());
        $this->assertSame('pilot', $rules->confidenceLevel());
        $this->assertSame(48.0, $rules->overtimeThresholdWeeklyHours());
        $this->assertSame([['up_to_hours' => null, 'multiplier' => 1.25]], $rules->overtimeRateTiers());
        $this->assertSame([7], $rules->weeklyRestDays());
        $this->assertSame(['daily', 'weekly', 'monthly'], $rules->supportedPayCycles());
        $this->assertSame('docs/payroll/TN_COMPLIANCE.md', $rules->complianceSource());
        $this->assertSame(554.736, $rules->minimumWage());
    }

    public function test_golden_tn_thirteenth_month_not_mandatory(): void
    {
        // Pas de 13ᵉ mois légal en Tunisie (pratique contractuelle).
        $this->assertFalse($this->rules()->thirteenthMonthMandatory());
        $this->assertSame('fully_taxable', $this->rules()->thirteenthMonthTaxTreatment());
    }

    public function test_golden_tn_rules_version_is_stable(): void
    {
        // Fingerprint déterministe (PA2-ARCH-004) portant le barème 2026 et
        // le calendrier de cotisations complet.
        $this->assertSame($this->rules()->rulesVersion(), $this->rules()->rulesVersion());
        $this->assertStringStartsWith('v1-', $this->rules()->rulesVersion());
        $this->assertSame(16, strlen(substr($this->rules()->rulesVersion(), 3)));
    }
}
