<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programme CEDEAO — #1826 : golden tests paie Côte d'Ivoire (CI).
 *
 * Méthodologie : valeur en dur calculée À LA MAIN dans le commentaire de
 * chaque test, jamais reprise du code. Référence légale : docs/payroll/
 * CI_COMPLIANCE.md (CGI CI 2024 art. 116-120, Code du travail art. 18/21,
 * CNSS CI). Statut des taux : PILOT — à valider par expert-comptable
 * OHADA-CI avant passage en production.
 *
 * Formules CI (CI_COMPLIANCE.md §1-2) :
 *   CNSS salariale   = min(brut, 1 647 315) × 3,2 %
 *   CNSS patronale   = min(brut, cap) × 4,5 % + min(brut, cap) × 5,75 %
 *                      + brut × 2,0 % (AT non plafonné)
 *   Assiette ITSAS   = (brut − CNSS salariale) × 0,80 (abattement 20 %,
 *                      non plafonné — appliqué sur base après CNSS, pilot)
 *   ITSAS annuel     = progressif sur 12 mois (tranches annuelles) → /12
 *   CN               = max(0, brut − 50 000) × 1,5 %
 *   Impôt total      = ITSAS mensuel + CN mensuelle
 */
class GoldenCiPayrollTest extends TestCase
{
    private function rules(): CedeaoPayrollRules
    {
        return new CedeaoPayrollRules('CI');
    }

    public function test_golden_ci_smig_75000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1-3), brut = SMIG 75 000 XOF :
        //   CNSS salariale = 75 000 × 3,2 % = 2 400
        //   Base ITSAS = (75 000 − 2 400) × 0,80 = 58 080 → annuel 696 960
        //   Tranches : 600 000 × 0 % + 96 960 × 2 % = 1 939,20 → ITSAS mensuel 161,60
        //   CN = (75 000 − 50 000) × 1,5 % = 375
        //   Impôt total = 536,60 · Net = 75 000 − 2 400 − 536,60 = 72 063,40
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(75000.0);

        $this->assertSame(2400.0, $charges['employee']);
        $this->assertSame(9187.50, $charges['employer']);

        $taxBase = 75000.0 - $charges['employee'];
        $itsas = $rules->calculateIncomeTax($taxBase);
        $cn = $rules->calculateBracketTax(75000.0);

        $this->assertSame(161.60, $itsas);
        $this->assertSame(375.0, $cn);
        $this->assertSame(72063.40, 75000.0 - $charges['employee'] - $itsas - $cn);
    }

    public function test_golden_ci_ouvrier_100000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 100 000 XOF :
        //   CNSS salariale = 3 200 · Base ITSAS = 96 800 × 0,80 = 77 440
        //   → annuel 929 280 → 2 % sur 329 280 = 6 585,60 → ITSAS 548,80
        //   CN = 50 000 × 1,5 % = 750 · Net = 100 000 − 3 200 − 1 298,80 = 95 501,20
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(100000.0);
        $itsas = $rules->calculateIncomeTax(100000.0 - $charges['employee']);
        $cn = $rules->calculateBracketTax(100000.0);

        $this->assertSame(3200.0, $charges['employee']);
        $this->assertSame(548.80, $itsas);
        $this->assertSame(750.0, $cn);
        $this->assertSame(95501.20, 100000.0 - $charges['employee'] - $itsas - $cn);
    }

    public function test_golden_ci_employe_200000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1 — exemple documenté), brut 200 000 :
        //   CNSS salariale = 6 400 · Base = 193 600 × 0,80 = 154 880
        //   → annuel 1 858 560 → 1 258 560 × 2 % = 25 171,20 → ITSAS 2 097,60
        //   CN = 150 000 × 1,5 % = 2 250 · Impôt total = 4 347,60 (conforme doc)
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(200000.0);
        $itsas = $rules->calculateIncomeTax(200000.0 - $charges['employee']);
        $cn = $rules->calculateBracketTax(200000.0);

        $this->assertSame(6400.0, $charges['employee']);
        $this->assertSame(2097.60, $itsas);
        $this->assertSame(2250.0, $cn);
        $this->assertSame(4347.60, $itsas + $cn);
    }

    public function test_golden_ci_technicien_400000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 400 000 :
        //   CNSS salariale = 12 800 · Base = 387 200 × 0,80 = 309 760
        //   → annuel 3 717 120 → 600k×0 % + 1,4M×2 % = 28 000
        //     + (3 717 120 − 2 000 000) × 21 % = 360 595,20 → total 388 595,20
        //   → ITSAS mensuel 32 382,93 · CN = 350 000 × 1,5 % = 5 250
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(400000.0);
        $itsas = $rules->calculateIncomeTax(400000.0 - $charges['employee']);
        $cn = $rules->calculateBracketTax(400000.0);

        $this->assertSame(12800.0, $charges['employee']);
        $this->assertSame(32382.93, $itsas);
        $this->assertSame(5250.0, $cn);
    }

    public function test_golden_ci_cadre_700000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 700 000 :
        //   CNSS salariale = 22 400 · Base = 677 600 × 0,80 = 542 080
        //   → annuel 6 504 960 → 28 000 (2 %) + 630 000 (21 % sur 3M)
        //     + (6 504 960 − 5 000 000) × 24,5 % = 368 715,20 → 1 026 715,20
        //   → ITSAS mensuel 85 559,60 · CN = 650 000 × 1,5 % = 9 750
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(700000.0);
        $itsas = $rules->calculateIncomeTax(700000.0 - $charges['employee']);
        $cn = $rules->calculateBracketTax(700000.0);

        $this->assertSame(22400.0, $charges['employee']);
        $this->assertSame(85559.60, $itsas);
        $this->assertSame(9750.0, $cn);
    }

    public function test_golden_ci_plafond_cnss_1647315(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §2), brut = plafond CNSS 1 647 315 :
        //   CNSS salariale = 1 647 315 × 3,2 % = 52 714,08
        //   Patronale = 74 129,18 + 94 720,61 + 32 946,30 (AT 2 %) = 201 796,09
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(1647315.0);

        $this->assertSame(52714.08, $charges['employee']);
        $this->assertSame(201796.09, $charges['employer']);
    }

    public function test_golden_ci_au_dela_du_plafond_2000000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §2 — exemple documenté), brut 2 000 000 :
        //   salariale = 1 647 315 × 3,2 % = 52 714,08 (plafonné)
        //   patronale = 74 129,18 + 94 720,61 + 2 000 000 × 2 % = 208 849,79
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(2000000.0);

        $this->assertSame(52714.08, $charges['employee']);
        $this->assertSame(208849.79, $charges['employer']);
    }

    public function test_golden_ci_cn_sous_seuil_49000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1) : CN = 0 sous 50 000 XOF.
        $rules = $this->rules();

        $this->assertSame(0.0, $rules->calculateBracketTax(49000.0));
    }

    public function test_golden_ci_cn_active_200000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1) : CN = (200 000 − 50 000) × 1,5 %
        // = 2 250 XOF.
        $rules = $this->rules();

        $this->assertSame(2250.0, $rules->calculateBracketTax(200000.0));
    }

    #[DataProvider('itsasProvider')]
    public function test_golden_ci_itsas_progressive(float $gross, float $cnss, float $expectedItsas): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1) : assiette = (gross − cnss) × 0,80,
        // ITSAS = progressif annuel / 12 sur les tranches CGI CI art. 116-120.
        $rules = $this->rules();

        $this->assertSame($expectedItsas, $rules->calculateIncomeTax($gross - $cnss));
    }

    public static function itsasProvider(): array
    {
        return [
            'tranche 0 % (annuel ≤ 600k)'     => [50000.0, 1600.0, 0.0],
            // (150 000 − 4 800) × 0,8 = 116 160 → annuel 1 393 920 → 2 % sur 793 920
            // = 15 878,40 → mensuel 1 323,20
            'tranche 2 % (600k–2M)'           => [150000.0, 4800.0, 1323.20],
            // (300 000 − 9 600) × 0,8 = 232 320 → annuel 2 787 840 → 28 000 (2 %)
            // + 787 840 × 21 % = 165 446,40 → 193 446,40 → mensuel 16 120,53
            'tranche 21 % (2M–5M)'            => [300000.0, 9600.0, 16120.53],
            'tranche 24,5 % (5M–10M)'         => [700000.0, 22400.0, 85559.60],
            // (1 200 000 − 38 400) × 0,8 = 929 280 → annuel 11 151 360 → 28 000
            // + 630 000 + 1 225 000 + 1 151 360 × 29 % = 2 216 894,40 → 184 741,20
            'tranche 29 % (> 10M)'            => [1200000.0, 38400.0, 184741.20],
        ];
    }

    public function test_golden_ci_hs_tiers(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §4 — Code du travail art. 21) :
        //  8 premières h HS → +15 % (1,15) · h 9 à 14 → +35 % (1,35) ·
        //  au-delà / nuit / dimanche → +50 % (1,50).
        $rules = $this->rules();

        $tiers = $rules->overtimeRateTiers();

        $this->assertSame([
            ['up_to_hours' => 8.0, 'multiplier' => 1.15],
            ['up_to_hours' => 14.0, 'multiplier' => 1.35],
            ['up_to_hours' => null, 'multiplier' => 1.50],
        ], $tiers);
    }

    public function test_golden_ci_13eme_mois_obligatoire(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §5) : 13ème mois légal pour la CI
        // (conventions de branche) — le moteur injecte la ligne en décembre.
        $rules = $this->rules();

        $this->assertTrue($rules->thirteenthMonthMandatory());
        $this->assertSame('fully_taxable', $rules->thirteenthMonthTaxTreatment());
    }

    #[DataProvider('preavisProvider')]
    public function test_golden_ci_preavis(float $years, float $expectedDays): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §6 — Code du travail art. 18) :
        //  < 5 ans → 30 j · 5-10 ans → 60 j · ≥ 10 ans → 90 j
        //  (palier employé/technicien — pilot, à valider).
        $rules = $this->rules();

        $this->assertSame($expectedDays, $rules->noticePeriodDays($years));
    }

    public static function preavisProvider(): array
    {
        return [
            'moins de 5 ans'  => [2.0, 30.0],
            '5 à 10 ans'      => [7.0, 60.0],
            '10 ans et plus'  => [12.0, 90.0],
        ];
    }

    public function test_golden_ci_prorata_entree_15(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md) : prorata entrée le 15 → 12,06 j/22
        // (même mécanique F-05 que DZ) : 22 × 17/31 = 12,06.
        $this->assertSame(32890.91, (new PayrollCalculator())->computeProratedBase(60000.0, 22.0, 12.06));
    }

    public function test_golden_ci_prorata_sortie_10(): void
    {
        // Calcul manuel : sortie le 10 → 7,10 j/22 : 22 × 10/31 = 7,10.
        $this->assertSame(19363.64, (new PayrollCalculator())->computeProratedBase(60000.0, 22.0, 7.10));
    }

    public function test_golden_ci_cnss_employee_zero_on_zero_salary(): void
    {
        // Calcul manuel : pas de salaire → pas de cotisations, pas d'impôt.
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(0.0);

        $this->assertSame(0.0, $charges['employee']);
        $this->assertSame(0.0, $charges['employer']);
        $this->assertSame(0.0, $rules->calculateIncomeTax(0.0));
        $this->assertSame(0.0, $rules->calculateBracketTax(0.0));
    }

    public function test_golden_ci_abattement_20_pct(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1) : abattement frais pro = 20 %
        // du brut, non plafonné — appliqué sur la base après CNSS.
        $rules = $this->rules();

        $abatement = $rules->professionalExpensesDeduction();

        $this->assertSame(20.0, $abatement['rate']);
        $this->assertNull($abatement['cap']);
    }

    public function test_golden_ci_flat_tax_label(): void
    {
        // Calcul manuel : la taxe forfaitaire CI porte le libellé CN.
        $rules = $this->rules();

        $this->assertSame('Contribution Nationale (CN)', $rules->flatPayrollTaxLabel());
    }

    public function test_golden_ci_minimum_wage_smig(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §3) : SMIG 75 000 XOF/mois.
        $rules = $this->rules();

        $this->assertSame(75000.0, $rules->minimumWage());
    }

    public function test_golden_ci_currency_and_timezone(): void
    {
        // Calcul manuel : XOF (BCEAO), timezone Abidjan (UTC).
        $rules = $this->rules();

        $this->assertSame('XOF', $rules->currency());
        $this->assertSame('Africa/Abidjan', $rules->timezone());
    }

    public function test_golden_ci_cnss_salariale_high_brut_capped(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §2) : le plafond s'applique à la
        // retraite salariale — brut 3 000 000 → salariale = plafond × 3,2 %.
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(3000000.0);

        $this->assertSame(52714.08, $charges['employee']);
        // Patronale : 74 129,18 + 94 720,61 + 60 000 (AT 2 % non plafonné).
        $this->assertSame(228849.79, $charges['employer']);
    }
}
