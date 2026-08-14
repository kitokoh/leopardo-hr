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
 *   Assiette ITSAS   = brut − CNSS salariale − abattement 20 % du BRUT
 *                      (non plafonné — formule légale, fix #1893)
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
        //   Base ITSAS = 75 000 − 2 400 − abattement 20 % du BRUT (15 000)
        //     = 57 600 → annuel 691 200 → 91 200 × 2 % = 1 824 → ITSAS 152,00
        //   CN = (75 000 − 50 000) × 1,5 % = 375
        //   Impôt total = 527,00 · Net = 75 000 − 2 400 − 527,00 = 72 073,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(75000.0);

        $this->assertSame(2400.0, $charges['employee']);
        $this->assertSame(9187.50, $charges['employer']);

        $taxBase = 75000.0 - $charges['employee'];
        $itsas = $rules->calculateIncomeTax($taxBase, 12, 75000.0);
        $cn = $rules->calculateBracketTax(75000.0);

        $this->assertSame(152.00, $itsas);
        $this->assertSame(375.0, $cn);
        $this->assertSame(72073.00, 75000.0 - $charges['employee'] - $itsas - $cn);
    }

    public function test_golden_ci_ouvrier_100000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 100 000 XOF :
        //   CNSS salariale = 3 200 · Base ITSAS = 100 000 − 3 200 − 20 000
        //     = 76 800 → annuel 921 600 → 321 600 × 2 % = 6 432 → ITSAS 536,00
        //   CN = 50 000 × 1,5 % = 750 · Net = 100 000 − 3 200 − 1 286,00 = 95 514,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(100000.0);
        $itsas = $rules->calculateIncomeTax(100000.0 - $charges['employee'], 12, 100000.0);
        $cn = $rules->calculateBracketTax(100000.0);

        $this->assertSame(3200.0, $charges['employee']);
        $this->assertSame(536.00, $itsas);
        $this->assertSame(750.0, $cn);
        $this->assertSame(95514.00, 100000.0 - $charges['employee'] - $itsas - $cn);
    }

    public function test_golden_ci_employe_200000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1 — exemple documenté), brut 200 000 :
        //   CNSS salariale = 6 400 · Base ITSAS = 200 000 − 6 400 − 40 000
        //     = 153 600 → annuel 1 843 200 → 1 243 200 × 2 % = 24 864 → ITSAS 2 072,00
        //   CN = 150 000 × 1,5 % = 2 250 · Impôt total = 4 322,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(200000.0);
        $itsas = $rules->calculateIncomeTax(200000.0 - $charges['employee'], 12, 200000.0);
        $cn = $rules->calculateBracketTax(200000.0);

        $this->assertSame(6400.0, $charges['employee']);
        $this->assertSame(2072.00, $itsas);
        $this->assertSame(2250.0, $cn);
        $this->assertSame(4322.00, $itsas + $cn);
    }

    public function test_golden_ci_technicien_400000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 400 000 :
        //   CNSS salariale = 12 800 · Base ITSAS = 400 000 − 12 800 − 80 000
        //     = 307 200 → annuel 3 686 400 → 28 000 (2 % sur 1,4M)
        //     + (3 686 400 − 2 000 000) × 21 % = 354 144 → total 382 144
        //   → ITSAS mensuel 31 845,33 · CN = 350 000 × 1,5 % = 5 250
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(400000.0);
        $itsas = $rules->calculateIncomeTax(400000.0 - $charges['employee'], 12, 400000.0);
        $cn = $rules->calculateBracketTax(400000.0);

        $this->assertSame(12800.0, $charges['employee']);
        $this->assertSame(31845.33, $itsas);
        $this->assertSame(5250.0, $cn);
    }

    public function test_golden_ci_cadre_700000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 700 000 :
        //   CNSS salariale = 22 400 · Base ITSAS = 700 000 − 22 400 − 140 000
        //     = 537 600 → annuel 6 451 200 → 28 000 (2 %) + 630 000 (21 % sur 3M)
        //     + (6 451 200 − 5 000 000) × 24,5 % = 355 544 → 1 013 544
        //   → ITSAS mensuel 84 462,00 · CN = 650 000 × 1,5 % = 9 750
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(700000.0);
        $itsas = $rules->calculateIncomeTax(700000.0 - $charges['employee'], 12, 700000.0);
        $cn = $rules->calculateBracketTax(700000.0);

        $this->assertSame(22400.0, $charges['employee']);
        $this->assertSame(84462.00, $itsas);
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
        // Calcul manuel (CI_COMPLIANCE.md §1) : assiette = gross − cnss −
        // abattement 20 % du BRUT (fix #1893), ITSAS = progressif annuel / 12
        // sur les tranches CGI CI art. 116-120.
        $rules = $this->rules();

        $this->assertSame($expectedItsas, $rules->calculateIncomeTax($gross - $cnss, 12, $gross));
    }

/** @return array<string, list<mixed>> */
    public static function itsasProvider(): array
    {
        return [
            'tranche 0 % (annuel ≤ 600k)'     => [50000.0, 1600.0, 0.0],
            // 150 000 − 4 800 − 30 000 (20 % du brut) = 115 200 → annuel 1 382 400
            // → 2 % sur 782 400 = 15 648 → mensuel 1 304,00
            'tranche 2 % (600k–2M)'           => [150000.0, 4800.0, 1304.00],
            // 300 000 − 9 600 − 60 000 = 230 400 → annuel 2 764 800 → 28 000 (2 %)
            // + 764 800 × 21 % = 160 608 → 188 608 → mensuel 15 717,33
            'tranche 21 % (2M–5M)'            => [300000.0, 9600.0, 15717.33],
            'tranche 24,5 % (5M–10M)'         => [700000.0, 22400.0, 84462.00],
            // 1 200 000 − 38 400 − 240 000 = 921 600 → annuel 11 059 200 → 28 000
            // + 630 000 + 1 225 000 + 1 059 200 × 29 % = 2 190 168 → mensuel 182 514,00
            'tranche 29 % (> 10M)'            => [1200000.0, 38400.0, 182514.00],
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

/** @return array<string, list<mixed>> */
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
