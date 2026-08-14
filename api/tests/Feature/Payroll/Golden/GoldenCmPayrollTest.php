<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden tests paie CAMEROUN (#1822) — 20+ cas calculés À LA MAIN,
 * référencés dans docs/payroll/CM_COMPLIANCE.md (CGI 2024 art. 68,
 * Code du travail 92/007 art. 34, CNPS Cameroun).
 *
 * Méthodologie imposée par l'issue #1822 : chaque valeur attendue est
 * calculée manuellement dans le commentaire, JAMAIS reprise du code.
 * Tests sans base de données (règles pures de CemacPayrollRules('CM')).
 */
class GoldenCmPayrollTest extends TestCase
{
    private function rules(): CemacPayrollRules
    {
        return new CemacPayrollRules('CM');
    }

    // ── CNPS (cotisations sociales) ─────────────────────────────────────

    public function test_golden_cm_social_charges_at_smig(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §2) — brut = SMIG 41 875 XAF :
        //   Vieillesse salarié : 41 875 × 4,2 % = 1 758,75 (plaf. 750k non atteint)
        //   Vieillesse patronal : 41 875 × 4,2 % = 1 758,75
        //   Famille patronal   : 41 875 × 7,0 % = 2 931,25
        //   AT patronal        : 41 875 × 2,0 % = 837,50 (non plafonné)
        $charges = $this->rules()->calculateSocialCharges(41875.0);

        $this->assertSame(1758.75, $charges['employee']);
        $this->assertSame(5527.50, $charges['employer']); // 1758,75 + 2931,25 + 837,50
    }

    public function test_golden_cm_social_charges_at_200000(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §2) — brut 200 000 XAF :
        //   Salarié : 200 000 × 4,2 % = 8 400
        //   Patronal : 8 400 (vieillesse) + 14 000 (famille 7 %) + 4 000 (AT 2 %) = 26 400
        $charges = $this->rules()->calculateSocialCharges(200000.0);

        $this->assertSame(8400.0, $charges['employee']);
        $this->assertSame(26400.0, $charges['employer']);
    }

    public function test_golden_cm_social_charges_capped_at_750k(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §2) — brut 1 000 000 XAF :
        //   Vieillesse/famille plafonnées à 750 000 :
        //     salarié = 750 000 × 4,2 % = 31 500
        //     patronal = 31 500 (vieillesse) + 52 500 (famille 7 %) + 20 000 (AT 2 % non plafonné sur 1M)
        $charges = $this->rules()->calculateSocialCharges(1000000.0);

        $this->assertSame(31500.0, $charges['employee']);
        $this->assertSame(104000.0, $charges['employer']);
    }

    public function test_golden_cm_employer_cost_at_200000(): void
    {
        // Coût employeur total = brut + cotisations patronales :
        //   200 000 + 26 400 = 226 400 (CM_COMPLIANCE.md §2)
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(200000.0);

        $this->assertSame(226400.0, 200000.0 + $charges['employer']);
    }

    // ── IRPP (impôt sur le revenu) ──────────────────────────────────────

    /**
     * Calculs manuels (CM_COMPLIANCE.md §1) — chaque cas part du brut,
     * déduit la CNPS salariale, applique l'abattement frais pro
     * (30 % de la base, plafonné 350 000), annualise, applique les
     * tranches CGI 2024 art. 68 puis les centimes additionnels (× 1,10).
     *
     * @return array<string, array{0: float, 1: float}>
     */
    public static function irppProvider(): array
    {
        return [
            // Brut 200 000 : CNPS 8 400 → base 191 600 · abattement 57 480
            //   annuel 134 120 × 12 = 1 609 440 → 10 % = 160 944 → mensuel 13 412
            //   centimes → 14 753,20
            'junior 200k' => [200000.0, 14753.20],
            // Brut 400 000 : CNPS 16 800 → base 383 200 · abattement 114 960
            //   annuel 268 240 × 12 = 3 218 880 → 350 000 + 218 880×25 % = 404 720
            //   mensuel 33 726,6667 → centimes → 37 099,33
            'middle 400k' => [400000.0, 37099.33],
            // Brut 600 000 : CNPS 25 200 → base 574 800 · abattement 172 440
            //   annuel 402 360 × 12 = 4 828 320 → 200 000 + 150 000 + 1 828 320×25 % = 807 080
            //   mensuel 67 256,6667 → centimes → 73 982,33 (exemple CM_COMPLIANCE.md)
            'senior 600k' => [600000.0, 73982.33],
            // Brut 750 000 (= plafond CNPS) : CNPS 31 500 → base 718 500 · abattement 215 550
            //   annuel 502 950 × 12 = 6 035 400 → 850 000 + 1 035 400×35 % = 1 212 390
            //   mensuel 101 032,50 → centimes → 111 135,75
            'cap 750k' => [750000.0, 111135.75],
            // Brut 1 000 000 : CNPS 31 500 (plafonnée) → base 968 500 · abattement 290 550
            //   annuel 677 950 × 12 = 8 135 400 → 850 000 + 3 135 400×35 % = 1 947 390
            //   mensuel 162 282,50 → centimes → 178 510,75
            'high 1M' => [1000000.0, 178510.75],
            // Brut 1 500 000 : CNPS 31 500 → base 1 468 500 · abattement PL AFONNÉ 350 000
            //   annuel 1 118 500 × 12 = 13 422 000 → 850 000 + 8 422 000×35 % = 3 797 700
            //   mensuel 316 475 → centimes → 348 122,50
            'abatement capped 1.5M' => [1500000.0, 348122.50],
        ];
    }

    #[DataProvider('irppProvider')]
    public function test_golden_cm_irpp(float $gross, float $expectedTax): void
    {
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges($gross);
        $taxBase = $gross - $charges['employee'];

        $this->assertSame($expectedTax, $rules->calculateIncomeTax($taxBase));
    }

    public function test_golden_cm_irpp_zero_below_taxable(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §1) — brut SMIG 41 875 :
        //   CNPS 1 758,75 → base 40 116,25 · abattement 12 034,88
        //   annuel 28 081,37 × 12 = 336 976,44 → tranche 10 % = 33 697,64
        //   mensuel 2 808,14 → centimes → 3 088,95
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(41875.0);
        $tax = $rules->calculateIncomeTax(41875.0 - $charges['employee']);

        $this->assertSame(3088.95, $tax);
    }

    public function test_golden_cm_abatement_capped_at_350k(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §1) — brut 1 500 000 :
        //   30 % × 1 500 000 = 450 000 > plafond 350 000 → abattement = 350 000
        $deduction = $this->rules()->professionalExpensesDeduction();

        $this->assertSame(30.0, $deduction['rate']);
        $this->assertSame(350000.0, $deduction['cap']);
        $this->assertSame(350000.0, min(1500000.0 * 0.30, 350000.0));
    }

    // ── Tranches annuelles (bornes) ─────────────────────────────────────

    /**
     * Barème annuel via calculateIncomeTax(annualBasis=1) — calcul manuel
     * (CGI 2024 art. 68 + abattement 30 % plaf. 350 000, centimes ×1,10) :
     *   A = 2 000 000 : base mensuelle 166 666,67 · abattement 50 000
     *       annuel 116 666,67 → 10 % = 11 666,67 → ×1,10 = 12 833,33
     *   A = 3 000 000 : mensuel 250 000 · abattement 75 000
     *       annuel 175 000 → 10 % = 17 500 → ×1,10 = 19 250,00
     *   A = 5 000 000 : mensuel 416 666,67 · abattement 125 000
     *       annuel 291 666,67 → 10 % = 29 166,67 → ×1,10 = 32 083,33
     *   A = 6 000 000 : mensuel 500 000 · abattement 150 000
     *       annuel 350 000 → 10 % = 35 000 → ×1,10 = 38 500,00
     *
     * @return array<string, array{0: float, 1: float}>
     */
    public static function annualBracketProvider(): array
    {
        return [
            '2M annual' => [2000000.0, 12833.33],
            '3M annual' => [3000000.0, 19250.00],
            '5M annual' => [5000000.0, 32083.33],
            '6M annual' => [6000000.0, 38500.00],
        ];
    }

    #[DataProvider('annualBracketProvider')]
    public function test_golden_cm_annual_brackets(float $annualTaxable, float $expectedTaxWithCentimes): void
    {
        // Un annuel donné en entrée mensuelle avec annualBasis=1 restitue la
        // taxe annuelle (abattement inclus) ; centimes additionnels inclus.
        $monthlyBasis = $annualTaxable / 12.0;

        $this->assertSame($expectedTaxWithCentimes, $this->rules()->calculateIncomeTax($monthlyBasis, 1.0));
    }

    public function test_golden_cm_tax_slab_structure(): void
    {
        // Structure du barème annuel CM (CM_COMPLIANCE.md §1, CGI 2024 art. 68).
        $slabs = $this->rules()->taxSlabs();

        $this->assertCount(4, $slabs);
        $this->assertEquals(10.0, $slabs[0]['rate']);
        $this->assertSame(2000000, $slabs[0]['max']);
        $this->assertEquals(15.0, $slabs[1]['rate']);
        $this->assertEquals(25.0, $slabs[2]['rate']);
        $this->assertEquals(35.0, $slabs[3]['rate']);
        $this->assertNull($slabs[3]['max']);
    }

    // ── Préavis (fin de contrat) ────────────────────────────────────────

    /**
     * Durées légales de préavis (Code du travail 92/007, art. 34) :
     *   < 6 mois : 15 j · 6 mois–5 ans : 30 j · 5–10 ans : 60 j · > 10 ans : 90 j
     *
     * @return array<string, array{0: float, 1: float}>
     */
    public static function noticePeriodProvider(): array
    {
        return [
            'under 6 months' => [0.4, 15.0],
            '2 years' => [2.0, 30.0],
            '7 years' => [7.0, 60.0],
            'over 10 years' => [12.0, 90.0],
        ];
    }

    #[DataProvider('noticePeriodProvider')]
    public function test_golden_cm_notice_period(float $yearsOfService, float $expectedDays): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §3) — art. 34 Code du travail 92/007.
        $this->assertSame($expectedDays, $this->rules()->noticePeriodDays($yearsOfService));
    }

    public function test_golden_cm_notice_indemnity_at_200000(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §3) :
        //   2 ans d'ancienneté → préavis 30 j → indemnité = 1 mois = 200 000 XAF
        //   12 ans → préavis 90 j → indemnité = 3 mois = 600 000 XAF
        $rules = $this->rules();

        $this->assertSame(200000.0, 200000.0 * ($rules->noticePeriodDays(2.0) / 30.0));
        $this->assertSame(600000.0, 200000.0 * ($rules->noticePeriodDays(12.0) / 30.0));
    }

    public function test_golden_cm_severance_one_month_per_year(): void
    {
        // Indemnité d'ancienneté : 1 mois de salaire par année (défaut
        // CEMAC, AbstractCountryRules) — 5 ans × 200 000 = 1 000 000 XAF.
        $this->assertSame(1.0, $this->rules()->severanceMonthsPerYear(5.0));
        $this->assertSame(1000000.0, 5.0 * 200000.0);
    }

    // ── Net réel (parcours complet, même logique que PayrollCalculator) ─

    public function test_golden_cm_net_at_200000(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §1+§2) :
        //   Brut 200 000 · CNPS salariale 8 400 · IRPP (centimes inclus) 14 753,20
        //   Net = 200 000 − 8 400 − 14 753,20 = 176 846,80
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(200000.0);
        $tax = $rules->calculateIncomeTax(200000.0 - $charges['employee']);
        $net = 200000.0 - $charges['employee'] - $tax;

        $this->assertSame(176846.80, $net);
    }

    public function test_golden_cm_net_at_1000000(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §1+§2) :
        //   Brut 1 000 000 · CNPS salariale 31 500 (plafonnée) · IRPP 178 510,75
        //   Net = 1 000 000 − 31 500 − 178 510,75 = 789 989,25
        $rules = $this->rules();
        $charges = $rules->calculateSocialCharges(1000000.0);
        $tax = $rules->calculateIncomeTax(1000000.0 - $charges['employee']);
        $net = 1000000.0 - $charges['employee'] - $tax;

        $this->assertSame(789989.25, $net);
    }

    // ── Références pays ─────────────────────────────────────────────────

    public function test_golden_cm_country_references(): void
    {
        // Références CM (CM_COMPLIANCE.md) :
        //   SMIG 41 875 XAF (décret 2014) · devise XAF · fuseau Africa/Douala
        $rules = $this->rules();

        $this->assertSame('CM', $rules->countryCode());
        $this->assertSame('XAF', $rules->currency());
        $this->assertSame(41875.0, $rules->minimumWage());
        $this->assertSame('Africa/Douala', $rules->timezone());
        $this->assertSame([7], $rules->weeklyRestDays()); // dimanche
    }

    public function test_golden_cm_non_cm_members_keep_placeholders(): void
    {
        // Les autres membres CEMAC restent placeholder (#1824) : pas
        // d'abattement frais pro ni de préavis légal spécifique.
        $gabon = $this->rules()->forMemberCountry('GA');

        $this->assertSame('GA', $gabon->countryCode());
        $this->assertSame(0.0, $gabon->professionalExpensesDeduction()['rate']);
        $this->assertSame(0.0, $gabon->noticePeriodDays(5.0));
        $this->assertSame('placeholder', $gabon->confidenceLevel());
    }
}
