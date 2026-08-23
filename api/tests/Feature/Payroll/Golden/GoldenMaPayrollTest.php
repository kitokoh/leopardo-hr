<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\MoroccoPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests Maroc (MA) — issue #5248, constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN (docs/payroll/MA_COMPLIANCE.md),
 * pas reprise du code — une divergence = régression de conformité.
 *
 * Règles (pilot) — audit légal 2026 (issue #5248) :
 *   CNSS salariale 4,48 % plafonnée 6 000 MAD/mois · CNSS patronale 8,98 %
 *   plafonnée 6 000 · AMO 2,26 % / 4,11 % non plafonnée · IPE 0,19 % / 0,38 %
 *   plafonnée 6 000 · allocations familiales patronales 6,40 % non plafonnées
 *   · taxe formation professionnelle patronale 1,60 % non plafonnée.
 *   IR = progressif ANNUEL (CGI art. 73-I, LF 2025 : 0-40k 0 %, 40-60k 10 %,
 *   60-80k 20 %, 80-100k 30 %, 100-180k 34 %, >180k 37 %) / 12, assiette =
 *   brut − cotisations salariales − abattement frais pro (CGI art. 59-I,
 *   LF 2023 : 35 % si brut annuel < 78 000, 25 % au-delà, plancher 2 500,
 *   plafond 35 000).
 */
class GoldenMaPayrollTest extends TestCase
{
    private function rules(): MoroccoPayrollRules
    {
        return new MoroccoPayrollRules;
    }

    /**
     * Cas complets brut → cotisations → IR → net, calculés à la main.
     *
     * @return iterable<string, array{0: float, 1: float, 2: float, 3: float, 4: float}>
     */
    public static function goldenFullPathProvider(): iterable
    {
        yield 'SMIG 2026 (3 422,72)' => [3422.72, 237.19, 734.85, 0.00, 3185.53];
        yield 'Ouvrier 5 000' => [5000.0, 346.50, 1073.50, 0.00, 4653.50];
        yield 'Plafond CNSS exact (6 000)' => [6000.0, 415.80, 1288.20, 29.64, 5554.56];
        yield 'Au-dessus plafond + abattement 25 % (7 500)' => [7500.0, 449.70, 1469.85, 224.21, 6826.09];
        yield 'Cadre moyen (10 000)' => [10000.0, 506.20, 1772.60, 636.10, 8857.70];
        yield 'Cadre supérieur tranche 34 % (15 000)' => [15000.0, 619.20, 2378.10, 2064.47, 12316.33];
        yield 'Haut salaire tranche 37 % (60 000)' => [60000.0, 1636.20, 7827.60, 18232.11, 40131.69];
        yield 'Très haut salaire, CNSS/IPE plafonnées (100 000)' => [100000.0, 2540.20, 12671.60, 32697.63, 64762.17];
    }

    #[DataProvider('goldenFullPathProvider')]
    public function test_golden_ma_full_path(
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

    public function test_golden_ma_smig_2026_detail(): void
    {
        // Calcul manuel, brut = SMIG 2026 3 422,72 MAD (17,92 MAD/h × 191 h) :
        //   CNSS salariale = 3 422,72 × 4,48 % = 153,34 (sous plafond 6 000)
        //   AMO salariale   = 3 422,72 × 2,26 % = 77,35
        //   IPE salariale   = 3 422,72 × 0,19 % = 6,50
        //   → total salarié 237,19
        //   CNSS patronale  = 153,34 × 2 = 307,36 · AMO 140,67 · IPE 13,01
        //   · allocations familiales 219,05 · TFP 54,76 → total employeur 734,85
        //   Assiette IR = 3 422,72 − 237,19 = 3 185,53 → annuel 38 226,36
        //   Abattement 35 % = 13 379,23 → imposable 24 847,13 → tranche 0 % → IR 0
        //   Net = 3 422,72 − 237,19 = 3 185,53
        $rules = $this->rules();

        $this->assertSame(3422.72, $rules->minimumWage());

        $charges = $rules->calculateSocialCharges(3422.72);
        $this->assertSame(237.19, $charges['employee']);
        $this->assertSame(734.85, $charges['employer']);

        $this->assertSame(0.0, $rules->calculateIncomeTax(3422.72 - $charges['employee']));
        $this->assertSame(3185.53, round(3422.72 - $charges['employee'], 2));
    }

    public function test_golden_ma_cadre_moyen_10000_detail(): void
    {
        // Calcul manuel, brut 10 000 MAD :
        //   CNSS = 6 000 × 4,48 % = 268,80 (plafond) · AMO = 226,00
        //   · IPE = 6 000 × 0,19 % = 11,40 → salarié 506,20
        //   Abattement frais pro (CGI art. 59-I) : 25 % du brut ANNUEL
        //     = 25 % × (9 493,80 × 12) = 28 481,40 (≤ plafond 35 000)
        //     → assiette annuelle 113 925,60 − 28 481,40 = 85 444,20
        //     → tranche 30 % (fixe 18 000) : 85 444,20 × 30 % − 18 000
        //     = 7 633,26 → mensuel 636,10 (arrondi moteur — valeur exacte
        //     636,105 à la frontière flottante)
        //   Net = 10 000 − 506,20 − 636,10 = 8 857,70
        $charges = $this->rules()->calculateSocialCharges(10000.0);
        $this->assertSame(506.20, $charges['employee']);
        $this->assertSame(1772.60, $charges['employer']); // 538,80 CNSS + 411,00 AMO + 22,80 IPE + 640,00 AF + 160,00 TFP

        $tax = $this->rules()->calculateIncomeTax(10000.0 - $charges['employee']);
        $this->assertSame(636.10, $tax);
        $this->assertSame(8857.70, round(10000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ma_haut_salaire_60000_detail(): void
    {
        // Calcul manuel, brut 60 000 MAD :
        //   CNSS = 268,80 (plafond) · AMO = 1 356,00 · IPE = 11,40 → salarié 1 636,20
        //   Abattement 25 % (brut annuel ≥ 78 000) : 25 % × 700 365,60
        //     = 175 091,40 → plafonné à 35 000 → imposable annuel 665 365,60
        //     → tranche 37 % (fixe 27 400) : 665 365,60 × 37 % − 27 400
        //     = 218 785,27 → mensuel 18 232,11
        //   Net = 60 000 − 1 636,20 − 18 232,11 = 40 131,69
        $charges = $this->rules()->calculateSocialCharges(60000.0);
        $this->assertSame(1636.20, $charges['employee']);
        $this->assertSame(7827.60, $charges['employer']);

        $tax = $this->rules()->calculateIncomeTax(60000.0 - $charges['employee']);
        $this->assertSame(18232.11, $tax);
        $this->assertSame(40131.69, round(60000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_ma_legal_reference_slabs_2026(): void
    {
        // CGI Maroc art. 73-I — barème IR 2026 (LF 2025) : le référentiel
        // légal doit être EXACTEMENT la table 2026, sinon le seeder
        // (PayrollCountryConfigSeeder) rejouerait l'ancien barème en base.
        $this->assertSame([
            ['min' => 0, 'max' => 40000, 'rate' => 0, 'fixed_deduction' => 0],
            ['min' => 40001, 'max' => 60000, 'rate' => 10, 'fixed_deduction' => 4000],
            ['min' => 60001, 'max' => 80000, 'rate' => 20, 'fixed_deduction' => 10000],
            ['min' => 80001, 'max' => 100000, 'rate' => 30, 'fixed_deduction' => 18000],
            ['min' => 100001, 'max' => 180000, 'rate' => 34, 'fixed_deduction' => 22000],
            ['min' => 180001, 'max' => null, 'rate' => 37, 'fixed_deduction' => 27400],
        ], $this->rules()->legalReferenceTaxSlabs());
    }

    public function test_golden_ma_abatement_frais_professionnels(): void
    {
        // CGI art. 59-I (LF 2023) : 35 % si brut annuel < 78 000, 25 % si
        // ≥ 78 000, plancher 2 500 MAD, plafond 35 000 MAD/an.
        $rules = $this->rules();

        // Plancher : 2 000 × 35 % = 700 → relevé à 2 500.
        $this->assertSame(2500.0, $rules->moroccoProfessionalExpensesAbatement(2000.0));
        // 35 % : 10 000 × 35 % = 3 500 (bornes non atteintes).
        $this->assertSame(3500.0, $rules->moroccoProfessionalExpensesAbatement(10000.0));
        // Seuil exact : 78 000 × 25 % = 19 500 (le seuil bascule à 25 %).
        $this->assertSame(19500.0, $rules->moroccoProfessionalExpensesAbatement(78000.0));
        // 25 % : 120 000 × 25 % = 30 000.
        $this->assertSame(30000.0, $rules->moroccoProfessionalExpensesAbatement(120000.0));
        // Plafond : 200 000 × 25 % = 50 000 → plafonné à 35 000.
        $this->assertSame(35000.0, $rules->moroccoProfessionalExpensesAbatement(200000.0));
    }

    public function test_golden_ma_social_contributions_schedule(): void
    {
        // Audit légal 2026 : CNSS 4,48/8,98 % plafonnée 6 000 · AMO 2,26/4,11 %
        // non plafonnée · IPE 0,19/0,38 % plafonnée 6 000 · AF 6,40 % patronale
        // · TFP 1,60 % patronale (CNSS.ma, CLEISS, Upsilon 2026).
        $this->assertSame([
            ['name' => 'CNSS Salariale', 'code' => 'CNSS_EMP', 'type' => 'employee', 'rate' => 4.48, 'cap' => 6000],
            ['name' => 'CNSS Patronale', 'code' => 'CNSS_PAT', 'type' => 'employer', 'rate' => 8.98, 'cap' => 6000],
            ['name' => 'AMO Salariale', 'code' => 'AMO_EMP', 'type' => 'employee', 'rate' => 2.26, 'cap' => null],
            ['name' => 'AMO Patronale', 'code' => 'AMO_PAT', 'type' => 'employer', 'rate' => 4.11, 'cap' => null],
            ['name' => 'IPE Salariale (perte d\'emploi)', 'code' => 'IPE_EMP', 'type' => 'employee', 'rate' => 0.19, 'cap' => 6000],
            ['name' => 'IPE Patronale (perte d\'emploi)', 'code' => 'IPE_PAT', 'type' => 'employer', 'rate' => 0.38, 'cap' => 6000],
            ['name' => 'Allocations familiales (patronale)', 'code' => 'AF_PAT', 'type' => 'employer', 'rate' => 6.40, 'cap' => null],
            ['name' => 'Taxe de formation professionnelle (patronale)', 'code' => 'TFP_PAT', 'type' => 'employer', 'rate' => 1.60, 'cap' => null],
        ], $this->rules()->socialContributions());
    }

    public function test_golden_ma_charges_with_caps_disabled(): void
    {
        // Mode simulation (issue #1815) : sans plafonds, CNSS et IPE sont
        // calculées sur le brut entier. Brut 10 000 :
        //   salarié = 10 000 × (4,48 + 2,26 + 0,19) % = 693,00
        //   employeur = 10 000 × (8,98 + 4,11 + 0,38 + 6,40 + 1,60) % = 2 147,00
        $charges = $this->rules()->withCapsEnabled(false)->calculateSocialCharges(10000.0);

        $this->assertSame(693.00, $charges['employee']);
        $this->assertSame(2147.00, $charges['employer']);
    }

    public function test_golden_ma_country_parameters(): void
    {
        $rules = $this->rules();

        $this->assertSame('MA', $rules->countryCode());
        $this->assertSame('MAD', $rules->currency());
        $this->assertSame('Africa/Casablanca', $rules->timezone());
        $this->assertSame('fr', $rules->language());
        $this->assertSame('pilot', $rules->confidenceLevel());
        $this->assertSame(44.0, $rules->overtimeThresholdWeeklyHours());
        $this->assertSame([['up_to_hours' => null, 'multiplier' => 1.25]], $rules->overtimeRateTiers());
        $this->assertSame([7], $rules->weeklyRestDays());
        $this->assertSame(['daily', 'weekly', 'monthly'], $rules->supportedPayCycles());
        $this->assertSame('docs/payroll/MA_COMPLIANCE.md', $rules->complianceSource());
        $this->assertSame(3422.72, $rules->minimumWage());
    }

    public function test_golden_ma_thirteenth_month_not_mandatory(): void
    {
        // Pas de 13ᵉ mois légal au Maroc (pratique contractuelle) — le
        // moteur ne doit pas l'imposer.
        $this->assertFalse($this->rules()->thirteenthMonthMandatory());
        $this->assertSame('fully_taxable', $this->rules()->thirteenthMonthTaxTreatment());
    }

    public function test_golden_ma_rules_version_is_stable(): void
    {
        // Le fingerprint de version (PA2-ARCH-004) doit être déterministe
        // pour une même instance et changer avec les taux : il porte le
        // barème 2026 et le calendrier de cotisations complet.
        $this->assertSame(
            $this->rules()->rulesVersion(),
            $this->rules()->rulesVersion(),
        );

        $this->assertStringStartsWith('v1-', $this->rules()->rulesVersion());
        $this->assertSame(16, strlen(substr($this->rules()->rulesVersion(), 3)));
    }
}
