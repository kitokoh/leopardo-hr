<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\SenegalPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programme CEDEAO — #1828 : golden tests paie Sénégal (SN).
 *
 * Méthodologie : valeur en dur calculée À LA MAIN dans le commentaire de
 * chaque test, jamais reprise du code. Référence légale : docs/payroll/
 * SN_COMPLIANCE.md (CGI Sénégal, IPRES, CSS, Code du travail).
 * Statut : PILOT — valeurs à valider par expert-comptable sénégalais.
 *
 * Formules SN (SN_COMPLIANCE.md §1-7) :
 *   IPRES T1 salariale  = min(brut, 432 000) × 5,6 %      (plafond T1)
 *   IPRES T1 patronale  = min(brut, 432 000) × 8,4 %
 *   IPRES T2 cadres     = (min(brut, 2 160 000) − 432 000) × 2,4 % (sal.)
 *                        / × 3,6 % (pat.), si brut > 432 000
 *   CSS familiale 7 % (CIPRES/CLEISS #2473) + AT 1 % plafonnées à
 *   63 000 XOF + CFCE 3 %
 *   IR      = progressif annuel / 12 sur assiette = (brut − IPRES sal.)
 *            − abattement 30 % du BRUT (non plafonné), appliqué par
 *            SenegalPayrollRules::calculateIncomeTax (SN_COMPLIANCE §1/§6)
 *   TRIMF   = forfait mensuel par tranche de brut (6 tranches)
 */
class GoldenSnPayrollTest extends TestCase
{
    private function rules(): SenegalPayrollRules
    {
        return new SenegalPayrollRules;
    }

    public function test_golden_sn_smig_58900(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §3), brut = SMIG 58 900 XOF :
        //   TRIMF tranche 25 001–75 000 → 2 700
        //   IPRES salariale = 58 900 × 5,6 % = 3 298,40 (sous plafond T1)
        //   Patronal = 4 947,60 (T1) + 4 123 + 589 + 1 767 (CSS/AT/CFCE) = 11 426,60
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(58900.0);

        $this->assertSame(3298.40, $charges['employee']);
        $this->assertSame(11426.60, $charges['employer']);
        $this->assertSame(2700.0, $rules->calculateBracketTax(58900.0));
    }

    public function test_golden_sn_ouvrier_100000(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §1-3), brut 100 000 :
        //   IPRES salariale = 5 600 · Assiette IR = 100 000 − 5 600 = 94 400,
        //   abattement 30 % du BRUT (100 000 × 30 % = 30 000) → 64 400
        //   → annuel 772 800 → 20 % sur 142 800 = 28 560 → IR 2 380,00
        //   TRIMF tranche 75 001–150 000 → 5 400
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(100000.0);

        $this->assertSame(5600.0, $charges['employee']);
        $this->assertSame(16440.0, $charges['employer']);

        $base = 100000.0 - $charges['employee'];
        $this->assertSame(2380.0, $rules->calculateIncomeTax($base, 12, 100000.0));
        $this->assertSame(5400.0, $rules->calculateBracketTax(100000.0));
    }

    public function test_golden_sn_employe_250000(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §1-3), brut 250 000 :
        //   IPRES salariale = 14 000 · Assiette IR = 236 000, abattement 30 %
        //   du BRUT (250 000 × 30 % = 75 000) → 161 000
        //   → annuel 1 932 000 → 20 % sur 870 000 = 174 000
        //     + 30 % sur 432 000 = 129 600 → 303 600 → IR 25 300,00
        //   TRIMF tranche 150 001–350 000 → 9 000 · CFCE = 250 000 × 3 % = 7 500
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(250000.0);

        $this->assertSame(14000.0, $charges['employee']);
        $this->assertSame(33540.0, $charges['employer']);

        $base = 250000.0 - $charges['employee'];
        $this->assertSame(25300.0, $rules->calculateIncomeTax($base, 12, 250000.0));
        $this->assertSame(9000.0, $rules->calculateBracketTax(250000.0));
    }

    public function test_golden_sn_plafond_ipres_t1_432000(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §4 + #1913/#2014), brut = plafond
        // T1 432 000 :
        //   IPRES salariale = 432 000 × 5,6 % = 24 192 (T1 max, pas de T2)
        //   patronale = IPRES T1 8,4 % (36 288) + CSS famille 7 % plafonnée
        //     63 000 (4 410) + CSS AT 1 % plafonné 63 000 (630)
        //     + CFCE 3 % (12 960) = 54 288
        //   TRIMF tranche 350 001–700 000 → 18 000
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(432000.0);

        $this->assertSame(24192.0, $charges['employee']);
        $this->assertSame(54288.0, $charges['employer']);
        $this->assertSame(18000.0, $rules->calculateBracketTax(432000.0));
    }

    public function test_golden_sn_cadre_t1_t2_600000(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §4/§4bis), brut 600 000 :
        //   T1 = 24 192 · T2 = (600 000 − 432 000) × 2,4 % = 4 032
        //   → salariale 28 224 · Patronal : 36 288 (T1) + 6 048 (T2)
        //     + 4 410 + 630 + 18 000 (CSS/AT/CFCE) = 65 376
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(600000.0);

        $this->assertSame(28224.0, $charges['employee']);
        $this->assertSame(65376.0, $charges['employer']);
    }

    public function test_golden_sn_cadre_moyen_t2_1000000(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §4bis), brut 1 000 000 :
        //   T2 = (1 000 000 − 432 000) × 2,4 % = 13 632 → salariale 37 824
        //   TRIMF > 700 000 → 36 000
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(1000000.0);

        $this->assertSame(37824.0, $charges['employee']);
        $this->assertSame(91776.0, $charges['employer']);
        $this->assertSame(36000.0, $rules->calculateBracketTax(1000000.0));
    }

    public function test_golden_sn_cadre_haut_t2_max_2160000(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §4bis), brut = plafond T2 2 160 000 :
        //   T2 = (2 160 000 − 432 000) × 2,4 % = 41 472 → salariale 65 664
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(2160000.0);

        $this->assertSame(65664.0, $charges['employee']);
        $this->assertSame(168336.0, $charges['employer']);
    }

    public function test_golden_sn_haut_salaire_t2_plafonne_3000000(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §4bis), brut 3 000 000 :
        //   T2 plafonné à 2 160 000 → salariale identique à 2 160 000 (65 664)
        //   Patronal : 36 288 + 62 208 (T2) + 4 410 + 630 + 90 000 = 193 536
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(3000000.0);

        $this->assertSame(65664.0, $charges['employee']);
        $this->assertSame(193536.0, $charges['employer']);
    }

    public function test_golden_sn_css_family_and_at_caps_are_independent_from_ipres_t2(): void
    {
        $rules = $this->rules();

        $atT2Ceiling = $rules->calculateSocialCharges(2160000.0);
        $aboveAllCaps = $rules->calculateSocialCharges(3000000.0);

        self::assertSame(168336.0, $atT2Ceiling['employer']);
        self::assertSame(193536.0, $aboveAllCaps['employer']);
        self::assertSame(
            103536.0,
            $atT2Ceiling['employer'] - (2160000.0 * 3.0 / 100),
        );
        self::assertSame(
            103536.0,
            $aboveAllCaps['employer'] - (3000000.0 * 3.0 / 100),
        );
    }

    #[DataProvider('trimfProvider')]
    public function test_golden_sn_trimf_transches(float $gross, float $expectedTrimf): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §3) : TRIMF forfaitaire par tranche
        // de brut mensuel (900 / 2 700 / 5 400 / 9 000 / 18 000 / 36 000).
        $rules = $this->rules();

        $this->assertSame($expectedTrimf, $rules->calculateBracketTax($gross));
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function trimfProvider(): array
    {
        return [
            'tranche 1 (≤ 25k)'     => [25000.0, 900.0],
            'tranche 2 (25k-75k)'   => [75000.0, 2700.0],
            'tranche 3 (75k-150k)'  => [150000.0, 5400.0],
            'tranche 4 (150k-350k)' => [350000.0, 9000.0],
            'tranche 5 (350k-700k)' => [700000.0, 18000.0],
            'tranche 6 (> 700k)'    => [800000.0, 36000.0],
        ];
    }

    public function test_golden_sn_cfce_employeur(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §5) : CFCE = 3 % de la masse brute,
        // charge patronale uniquement — 250 000 × 3 % = 7 500.
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(250000.0);

        //   Patronal = T1 21 000 + CSS 4 410 + AT 630 + CFCE 7 500 = 33 540.
        $this->assertSame(33540.0, $charges['employer']);
    }

    public function test_golden_sn_abattement_30_pct(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §7) : abattement frais pro 30 % du
        // brut, non plafonné.
        $rules = $this->rules();

        $abatement = $rules->professionalExpensesDeduction();

        $this->assertSame(30.0, $abatement['rate']);
        $this->assertNull($abatement['cap']);
    }

    public function test_golden_sn_ir_tranche_40_pct(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §1), brut 3 000 000 :
        //   IPRES salariale 65 664 → assiette = 3 000 000 − 65 664 = 2 934 336 ;
        //   abattement 30 % du BRUT (3 000 000 × 30 % = 900 000) → 2 034 336
        //   → annuel 24 412 032 → 40 % au-delà de 13 500 000.
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(3000000.0);
        $base = 3000000.0 - $charges['employee'];

        $this->assertSame(726984.40, $rules->calculateIncomeTax($base, 12, 3000000.0));
    }

    public function test_golden_sn_prorata_entree_10(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md) : prorata entrée le 10 → 22 × 22/31
        // = 15,61 j (mécanique F-05). Base 250 000 × 15,61/22 = 177 386,36.
        $this->assertSame(177386.36, (new PayrollCalculator())->computeProratedBase(250000.0, 22.0, 15.61));
    }

    public function test_golden_sn_hs_tiers(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md / Code du travail) : +15 % les 8
        // premières h, jusqu'à +40 % au-delà ou de nuit.
        $rules = $this->rules();

        $this->assertSame([
            ['up_to_hours' => 8.0, 'multiplier' => 1.15],
            ['up_to_hours' => null, 'multiplier' => 1.40],
        ], $rules->overtimeRateTiers());

        $this->assertSame(40.0, $rules->overtimeThresholdWeeklyHours());
    }

    public function test_golden_sn_preavis_employe(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §8) : préavis employé/technicien =
        // 1 mois = 22 jours OUVRÉS (#2219, défaut, catégorie inconnue ou
        // 'general') — le moteur divise par les jours ouvrés du mois (22).
        $rules = $this->rules();

        $this->assertSame(22.0, $rules->noticePeriodDays(1.0));
        $this->assertSame(22.0, $rules->noticePeriodDays(10.0));
        $this->assertSame(22.0, $rules->noticePeriodDays(5.0, 'general'));
        $this->assertSame(22.0, $rules->noticePeriodDays(5.0, null));
    }

    public function test_golden_sn_preavis_par_categorie(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §8 — Code du travail Sénégal,
        // issue #2123) : la durée dépend de la catégorie du contrat
        // (employees.ipres_category) — issue #2219 : JOURS OUVRÉS :
        //   ouvriers → 8 j calendaires = 6 j ouvrés ·
        //   employés/techniciens → 1 mois = 22 · cadres → 3 mois = 66
        $rules = $this->rules();

        $this->assertSame(6.0, $rules->noticePeriodDays(5.0, 'ouvrier'));
        $this->assertSame(6.0, $rules->noticePeriodDays(5.0, 'worker'));
        $this->assertSame(22.0, $rules->noticePeriodDays(5.0, 'general'));
        $this->assertSame(66.0, $rules->noticePeriodDays(5.0, 'cadre'));
    }

    public function test_golden_sn_minimum_wage_currency_timezone(): void
    {
        // Calcul manuel (SN_COMPLIANCE.md) : SMIG 58 900 XOF, XOF/BCEAO,
        // timezone Dakar.
        $rules = $this->rules();

        $this->assertSame(58900.0, $rules->minimumWage());
        $this->assertSame('XOF', $rules->currency());
        $this->assertSame('Africa/Dakar', $rules->timezone());
    }

    public function test_golden_sn_cnss_employee_zero_on_zero_salary(): void
    {
        // Calcul manuel : pas de salaire → pas de cotisations ni impôt.
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(0.0);

        $this->assertSame(0.0, $charges['employee']);
        $this->assertSame(0.0, $charges['employer']);
        $this->assertSame(0.0, $rules->calculateIncomeTax(0.0));
        $this->assertSame(900.0, $rules->calculateBracketTax(0.0)); // tranche 1 forfaitaire
    }

    #[DataProvider('irProvider')]
    public function test_golden_sn_ir_progressive(float $gross, float $expectedIr): void
    {
        // Calcul manuel (SN_COMPLIANCE.md §1-2) : IR progressif annuel / 12 sur
        // assiette = brut − IPRES salariale, abattement 30 % du BRUT appliqué
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges($gross);
        $base = $gross - $charges['employee'];

        $this->assertSame($expectedIr, $rules->calculateIncomeTax($base, 12, $gross));
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function irProvider(): array
    {
        return [
            'tranche 20 % (annuel 772 800)' => [100000.0, 2380.0],
            'tranche 30 % (annuel 1 932 000)' => [250000.0, 25300.0],
            'tranche 35 % (annuel 4 701 312)' => [600000.0, 97454.93],
            'tranche 37 % (annuel 7 946 112)' => [1000000.0, 192094.93],
            'tranche 40 % (annuel 17 356 032)' => [2160000.0, 491784.40],
            'tranche 40 % (annuel 24 412 032)' => [3000000.0, 726984.40],
        ];
    }
}
