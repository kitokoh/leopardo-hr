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
 * Formules CI (CI_COMPLIANCE.md §1-2, réforme ITS 2024 — #1918) :
 *   CNSS salariale   = min(brut, 1 647 315) × 3,2 %
 *   CNSS patronale   = min(brut, cap) × 4,5 % + min(brut, cap) × 5,75 %
 *                      + brut × 2,0 % (AT non plafonné)
 *   ITS unifié       = progressif MENSUEL sur le BRUT (art. 119 bis CGI CI,
 *                      ordonnance 2023-718/719 — plus d'abattement, plus de
 *                      CN, plus d'annualisation)
 *   Tranches         = 0–75 000 : 0 % · 75 001–240 000 : 16 % ·
 *                      240 001–800 000 : 21 % · 800 001–2 400 000 : 24 % ·
 *                      2 400 001–8 000 000 : 28 % · > 8 000 000 : 32 %
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
        //   ITS 2024 = progressif MENSUEL sur le brut 75 000 → tranche
        //     0–75 000 @ 0 % → 0,00 (plus de CN ni d'abattement)
        //   Net = 75 000 − 2 400 − 0,00 = 72 600,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(75000.0);

        $this->assertSame(2400.0, $charges['employee']);
        $this->assertSame(9187.50, $charges['employer']);

        $taxBase = 75000.0 - $charges['employee'];
        $its = $rules->calculateIncomeTax($taxBase, 12, 75000.0);
        $cn = $rules->calculateBracketTax(75000.0);

        $this->assertSame(0.0, $its);
        $this->assertSame(0.0, $cn);
        $this->assertSame(72600.00, 75000.0 - $charges['employee'] - $its - $cn);
    }

    public function test_golden_ci_ouvrier_100000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 100 000 XOF :
        //   CNSS salariale = 3 200
        //   ITS 2024 = progressif mensuel sur le brut 100 000 :
        //     75 001–100 000 × 16 % = 25 000 × 16 % = 4 000,00 (plus de CN)
        //   Net = 100 000 − 3 200 − 4 000,00 = 92 800,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(100000.0);
        $its = $rules->calculateIncomeTax(100000.0 - $charges['employee'], 12, 100000.0);
        $cn = $rules->calculateBracketTax(100000.0);

        $this->assertSame(3200.0, $charges['employee']);
        $this->assertSame(4000.00, $its);
        $this->assertSame(0.0, $cn);
        $this->assertSame(92800.00, 100000.0 - $charges['employee'] - $its - $cn);
    }

    public function test_golden_ci_employe_200000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 200 000 :
        //   CNSS salariale = 6 400
        //   ITS 2024 = 75 001–200 000 × 16 % = 125 000 × 16 % = 20 000,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(200000.0);
        $its = $rules->calculateIncomeTax(200000.0 - $charges['employee'], 12, 200000.0);
        $cn = $rules->calculateBracketTax(200000.0);

        $this->assertSame(6400.0, $charges['employee']);
        $this->assertSame(20000.00, $its);
        $this->assertSame(0.0, $cn);
        $this->assertSame(20000.00, $its + $cn);
    }

    public function test_golden_ci_technicien_400000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 400 000 :
        //   CNSS salariale = 12 800
        //   ITS 2024 = 165 000 × 16 % (75 001–240 000) = 26 400
        //     + 160 000 × 21 % (240 001–400 000) = 33 600 → 60 000,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(400000.0);
        $its = $rules->calculateIncomeTax(400000.0 - $charges['employee'], 12, 400000.0);
        $cn = $rules->calculateBracketTax(400000.0);

        $this->assertSame(12800.0, $charges['employee']);
        $this->assertSame(60000.00, $its);
        $this->assertSame(0.0, $cn);
    }

    public function test_golden_ci_cadre_700000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1), brut 700 000 :
        //   CNSS salariale = 22 400
        //   ITS 2024 = 26 400 (75 001–240 000 × 16 %)
        //     + 460 000 × 21 % (240 001–700 000) = 96 600 → 123 000,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(700000.0);
        $its = $rules->calculateIncomeTax(700000.0 - $charges['employee'], 12, 700000.0);
        $cn = $rules->calculateBracketTax(700000.0);

        $this->assertSame(22400.0, $charges['employee']);
        $this->assertSame(123000.00, $its);
        $this->assertSame(0.0, $cn);
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

    public function test_golden_ci_cn_aboli_2024(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1, réforme 2024 — #1918) : la CN
        // (1,5 %) est supprimée/fusionnée dans l'ITS unique → 0 quel que
        // soit le brut (sous ou au-dessus de l'ancien seuil 50 000 XOF).
        $rules = $this->rules();

        $this->assertSame(0.0, $rules->calculateBracketTax(49000.0));
        $this->assertSame(0.0, $rules->calculateBracketTax(200000.0));
        $this->assertSame(0.0, $rules->calculateBracketTax(5000000.0));
    }

    #[DataProvider('itsProvider')]
    public function test_golden_ci_its_progressive(float $gross, float $cnss, float $expectedIts): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1 — ITS 2024) : ITS = progressif
        // MENSUEL sur le BRUT (art. 119 bis — plus d'abattement ni de CN).
        $rules = $this->rules();

        $this->assertSame($expectedIts, $rules->calculateIncomeTax($gross - $cnss, 12, $gross));
    }

    /**
     * @return array<string, array{float, float, float}>
     */
    public static function itsProvider(): array
    {
        return [
            'tranche 0 % (≤ 75k)'             => [50000.0, 1600.0, 0.0],
            // 150 000 → 75 001–150 000 × 16 % = 75 000 × 16 % = 12 000,00
            'tranche 16 % (75k–240k)'         => [150000.0, 4800.0, 12000.00],
            // 300 000 → 26 400 + 60 000 × 21 % = 12 600 → 39 000,00
            'tranche 21 % (240k–800k)'        => [300000.0, 9600.0, 39000.00],
            // 700 000 → 26 400 + 460 000 × 21 % = 96 600 → 123 000,00
            'tranche 24 % (800k–2,4M)'        => [700000.0, 22400.0, 123000.00],
            // 3 000 000 → 26 400 + 117 600 + 1 600 000 × 24 % = 384 000
            //   + 600 000 × 28 % = 168 000 → 696 000,00
            'tranche 28 % (2,4M–8M)'          => [3000000.0, 96000.0, 696000.00],
            // 10 000 000 → 26 400 + 117 600 + 384 000 + 5 600 000 × 28 %
            //   = 1 568 000 + 2 000 000 × 32 % = 640 000 → 2 736 000,00
            'tranche 32 % (> 8M)'             => [10000000.0, 320000.0, 2736000.00],
            // 12 000 000 (> 10 M — suivi #1918) → 2 736 000
            //   + 4 000 000 × 32 % = 1 280 000 → 3 376 000,00
            'tranche 32 % (> 10M)'            => [12000000.0, 384000.0, 3376000.00],
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

    /**
     * @return array<string, array{float, float}>
     */
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

    public function test_golden_ci_abattement_supprime_2024(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §1, réforme 2024 — #1918) : l'ITS
        // s'applique sur le BRUT sans abattement frais pro (art. 119 bis).
        $rules = $this->rules();

        $abatement = $rules->professionalExpensesDeduction();

        $this->assertSame(0.0, $abatement['rate']);
        $this->assertNull($abatement['cap']);
    }

    public function test_golden_ci_flat_tax_label(): void
    {
        // Calcul manuel (#1918) : la CN abolie → la CI n'a plus de libellé
        // dédié — libellé moteur par défaut (jamais affiché, montant nul).
        $rules = $this->rules();

        $this->assertSame('Taxe de minimum fiscal', $rules->flatPayrollTaxLabel());
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

    /**
     * #1918 — régression : le bulletin CI complet (pipeline calculateRun)
     * applique l'ITS 2024 unique sur le brut.
     * Net = 100 000 − CNSS 3 200 − ITS 4 000 = 92 800 (voir
     * test_golden_ci_ouvrier_100000) ; plus aucune taxe forfaitaire (CN
     * abolie) à déduire.
     */
    public function test_golden_ci_full_run_its_2024(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = \App\Core\Tenant\Domain\Models\Company::factory()->create([
            'country' => 'CI',
            'currency' => 'XOF',
        ]);
        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 100000,
        ]);

        \App\Modules\Payroll\Domain\Models\SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille CI test',
            'base_salary' => 100000,
            'currency' => 'XOF',
            'country_code' => 'CI',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        /** @var \App\Modules\Payroll\Domain\Models\PayrollRun $run */
        $run = \App\Modules\Payroll\Domain\Models\PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'CI',
            'status' => 'draft',
        ]);

        (new \App\Modules\Payroll\Infrastructure\Services\PayrollCalculator)->calculateRun($run);

        /** @var \App\Modules\Payroll\Domain\Models\PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();

        $this->assertSame(100000.0, (float) $slip->gross_salary);
        $this->assertSame(7200.0, (float) $slip->total_deductions);
        $this->assertSame(92800.0, (float) $slip->net_salary);
    }
}
