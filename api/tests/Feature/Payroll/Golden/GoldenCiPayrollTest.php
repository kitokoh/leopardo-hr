<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\RefreshTenantDatabase;
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
 *   CNSS patronale   = min(brut, 1 647 315) × 4,5 %
 *                      + min(brut, 70 000) × 5,75 % + min(brut, 70 000) × 2,0 %
 *                      (famille/AT plafonnées séparément à 70 000, #1913 CNPS)
 *   ITS unifié       = progressif MENSUEL sur le BRUT (art. 119 bis CGI CI,
 *                      ordonnance 2023-718/719 — plus d'abattement, plus de
 *                      CN, plus d'annualisation)
 *   Tranches         = 0–75 000 : 0 % · 75 001–240 000 : 16 % ·
 *                      240 001–800 000 : 21 % · 800 001–2 400 000 : 24 % ·
 *                      2 400 001–8 000 000 : 28 % · > 8 000 000 : 32 %
 */
class GoldenCiPayrollTest extends TestCase
{
    use RefreshTenantDatabase;

    private function rules(): CedeaoPayrollRules
    {
        return new CedeaoPayrollRules('CI');
    }

    public function test_golden_ci_smig_75000(): void
    {

        // Calcul manuel (CI_COMPLIANCE.md §1-4), brut = SMIG 75 000 XOF :
        //   CNSS salariale = 75 000 × 3,2 % = 2 400 (plafond retraite 1 647 315)
        //   Patronal : retraite 4,5 % × 75 000 = 3 375 · famille 5,75 % et AT
        //     2,0 % plafonnées à 70 000 (guide CNPS, #1913) = 4 025 + 1 400
        //     → 8 800,00

        //   ITS 2024 = progressif MENSUEL sur le brut 75 000 → tranche
        //     0–75 000 @ 0 % → 0,00 (plus de CN ni d'abattement)
        //   Net = 75 000 − 2 400 − 0,00 = 72 600,00
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(75000.0);

        $this->assertSame(2400.0, $charges['employee']);
        $this->assertSame(8800.0, $charges['employer']);

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
        // Calcul manuel (CI_COMPLIANCE.md §4), brut = plafond retraite
        // 1 647 315 : famille et AT restent plafonnées séparément à 70 000.
        //   CNSS salariale = 1 647 315 × 3,2 % = 52 714,08
        //   Patronale = 74 129,18 + 4 025,00 + 1 400,00 = 79 554,18
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(1647315.0);

        $this->assertSame(52714.08, $charges['employee']);
        $this->assertSame(79554.18, $charges['employer']);
    }

    public function test_golden_ci_au_dela_du_plafond_2000000(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §4), brut 2 000 000 :
        //   salariale = 1 647 315 × 3,2 % = 52 714,08 (plafonné)
        //   patronale = 74 129,18 + 4 025,00 + 1 400,00 = 79 554,18
        //   Les branches famille/AT ne dérivent plus avec le brut.
        $rules = $this->rules();

        $charges = $rules->calculateSocialCharges(2000000.0);

        $this->assertSame(52714.08, $charges['employee']);
        $this->assertSame(79554.18, $charges['employer']);
    }

    public function test_golden_ci_family_and_at_caps_are_independent_from_retirement_cap(): void
    {
        $rules = $this->rules();

        $atRetirementCap = $rules->calculateSocialCharges(1647315.0);
        $aboveAllCaps = $rules->calculateSocialCharges(3000000.0);

        self::assertSame($atRetirementCap['employer'], $aboveAllCaps['employer']);
        self::assertSame(79554.18, $aboveAllCaps['employer']);
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
            'tranche 0 % (≤ 75k)' => [50000.0, 1600.0, 0.0],
            // 150 000 → 75 001–150 000 × 16 % = 75 000 × 16 % = 12 000,00
            'tranche 16 % (75k–240k)' => [150000.0, 4800.0, 12000.00],
            // 300 000 → 26 400 + 60 000 × 21 % = 12 600 → 39 000,00
            'tranche 21 % (240k–800k)' => [300000.0, 9600.0, 39000.00],
            // 700 000 → 26 400 + 460 000 × 21 % = 96 600 → 123 000,00
            'tranche 24 % (800k–2,4M)' => [700000.0, 22400.0, 123000.00],
            // 3 000 000 → 26 400 + 117 600 + 1 600 000 × 24 % = 384 000
            //   + 600 000 × 28 % = 168 000 → 696 000,00
            'tranche 28 % (2,4M–8M)' => [3000000.0, 96000.0, 696000.00],
            // 10 000 000 → 26 400 + 117 600 + 384 000 + 5 600 000 × 28 %
            //   = 1 568 000 + 2 000 000 × 32 % = 640 000 → 2 736 000,00
            'tranche 32 % (> 8M)' => [10000000.0, 320000.0, 2736000.00],
            // 12 000 000 (> 10 M — suivi #1918) → 2 736 000
            //   + 4 000 000 × 32 % = 1 280 000 → 3 376 000,00
            'tranche 32 % (> 10M)' => [12000000.0, 384000.0, 3376000.00],
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
        // Calcul manuel (CI_COMPLIANCE.md §8 — Code du travail art. 18) :
        //  niveau employé/technicien (pilot) : < 5 ans → 22 j · ≥ 5 ans → 44 j
        //  (JOURS OUVRÉS #2219 — ex 30/60 j calendaires).
        //  Cadres → 66 j et ouvriers → 6/11 j (catégorie via ipres_category).
        $rules = $this->rules();

        $this->assertSame($expectedDays, $rules->noticePeriodDays($years));
    }

    #[DataProvider('preavisParCategorieProvider')]
    public function test_golden_ci_preavis_par_categorie(string $category, float $years, float $expectedDays): void
    {
        // Calcul manuel (CI_COMPLIANCE.md §8 — Code du travail art. 18) :
        //  ouvriers : < 5 ans → 6 j · ≥ 5 ans → 11 j (JOURS OUVRÉS #2219, ex 8/15)
        //  employés/techniciens : < 5 ans → 22 j · ≥ 5 ans → 44 j (ex 30/60)
        //  cadres : 66 j (3 mois) quelle que soit l'ancienneté (ex 90)
        //  — la catégorie est portée par employees.ipres_category (#2264).
        $rules = $this->rules();

        $this->assertSame($expectedDays, $rules->noticePeriodDays($years, $category));
    }

    /**
     * @return array<string, array{string, float, float}>
     */
    public static function preavisParCategorieProvider(): array
    {
        // CI_COMPLIANCE.md §8 (Code du travail art. 18, implémenté #2372) :
        //  ouvriers 6/11 j · employés/techniciens 22/44 j · cadres 66 j
        //  (JOURS OUVRÉS #2219, conversion 30→22 / 60→44 / 90→66 / 8→6 / 15→11).
        return [
            'ouvrier moins de 5 ans' => ['ouvrier', 2.0, 6.0],
            'ouvrier 5 ans et plus' => ['ouvrier', 7.0, 11.0],
            'worker 10 ans' => ['worker', 10.0, 11.0],
            'employe moins de 5 ans' => ['employee', 2.0, 22.0],
            'employe 5 ans et plus' => ['employee', 7.0, 44.0],
            'technicien 12 ans' => ['technician', 12.0, 44.0],
            'cadre 2 ans' => ['cadre', 2.0, 66.0],
            'cadre 15 ans' => ['cadre', 15.0, 66.0],
        ];
    }

    /**
     * @return array<string, array{float, float}>
     */
    public static function preavisProvider(): array
    {
        return [
            'moins de 5 ans' => [2.0, 22.0],
            '5 à 10 ans' => [7.0, 44.0],
            '10 ans et plus' => [12.0, 44.0],
        ];
    }

    public function test_golden_ci_prorata_entree_15(): void
    {
        // Calcul manuel (CI_COMPLIANCE.md) : prorata entrée le 15 → 12,06 j/22
        // (même mécanique F-05 que DZ) : 22 × 17/31 = 12,06.
        $this->assertSame(32890.91, (new PayrollCalculator)->computeProratedBase(60000.0, 22.0, 12.06));
    }

    public function test_golden_ci_prorata_sortie_10(): void
    {
        // Calcul manuel : sortie le 10 → 7,10 j/22 : 22 × 10/31 = 7,10.
        $this->assertSame(19363.64, (new PayrollCalculator)->computeProratedBase(60000.0, 22.0, 7.10));
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
        // Patronale : 74 129,18 + 4 025,00 (famille 70 000 × 5,75 %) + 1 400,00 (AT 70 000 × 2 %), caps #1913.
        $this->assertSame(79554.18, $charges['employer']);
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
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'CI',
            'currency' => 'XOF',
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'salary_type' => 'fixed',
            'salary_base' => 100000,
            // Golden CI 2024 : contrat actif pendant toute la période testée.
            // Sans dates explicites, EmployeeFactory peut proratiser le brut.
            'contract_start' => '2026-01-01',
            'contract_end' => null,
        ]);

        SalaryStructure::create([
            'company_id' => $company->id,
            'name' => 'Grille CI test',
            'base_salary' => 100000,
            'currency' => 'XOF',
            'country_code' => 'CI',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'CI',
            'status' => 'draft',
        ]);

        (new PayrollCalculator)->calculateRun($run);

        /** @var PaySlip $slip */
        $slip = $run->paySlips()->firstOrFail();

        $this->assertSame(100000.0, (float) $slip->gross_salary);
        $this->assertSame(7200.0, (float) $slip->total_deductions);
        $this->assertSame(92800.0, (float) $slip->net_salary);
    }

    public function test_golden_ci_ricf_scale_art_120(): void
    {
        // RICF — calcul manuel (CGI CI art. 120, réforme ord. 2023-718/719,
        // effet 01/01/2024, CI_COMPLIANCE.md §1) : réduction d'impôt pour
        // charges de famille imputable sur l'ITS brut, 11 000 XOF/mois par
        // demi-part au-delà de 1 part, plafonnée à 44 000 (5 parts) :
        //   1 part → 0 · 1,5 → 5 500 · 2 → 11 000 · 2,5 → 16 500 ·
        //   3 → 22 000 · 3,5 → 27 500 · 4 → 33 000 · 4,5 → 38 500 ·
        //   5 → 44 000.
        $rules = $this->rules();

        $this->assertSame(0.0, $rules->familyTaxReduction(1.0));
        $this->assertSame(5500.0, $rules->familyTaxReduction(1.5));
        $this->assertSame(11000.0, $rules->familyTaxReduction(2.0));
        $this->assertSame(16500.0, $rules->familyTaxReduction(2.5));
        $this->assertSame(22000.0, $rules->familyTaxReduction(3.0));
        $this->assertSame(27500.0, $rules->familyTaxReduction(3.5));
        $this->assertSame(33000.0, $rules->familyTaxReduction(4.0));
        $this->assertSame(38500.0, $rules->familyTaxReduction(4.5));
        $this->assertSame(44000.0, $rules->familyTaxReduction(5.0));
        // Plafond légal : le nombre de parts ne peut pas dépasser 5.
        $this->assertSame(44000.0, $rules->familyTaxReduction(6.0));
        // Défaut 1 part (célibataire sans enfant) → aucune réduction.
        $this->assertSame(0.0, $rules->familyTaxReduction());
        // Les autres membres CEDEAO (BF) n'ont pas de RICF implémentée.
        $this->assertSame(0.0, (new CedeaoPayrollRules('BF'))->familyTaxReduction(3.0));
    }

    public function test_golden_ci_ricf_marie_1_enfant_brut_100000(): void
    {
        // Calcul manuel (#2117) : brut 100 000, 2,5 parts (marié 1 enfant) :
        //   CNSS salariale = 100 000 × 3,2 % = 3 200
        //   ITS brut 2024 = (100 000 − 75 000) × 16 % = 4 000
        //   RICF (art. 120) = 11 000 × (2,5 − 1) = 16 500 → imputable sur
        //     l'impôt brut → ITS net = max(0, 4 000 − 16 500) = 0
        //   Net = 100 000 − 3 200 − 0 = 96 800
        $rules = $this->rules();

        $this->assertSame(16500.0, $rules->familyTaxReduction(2.5));

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(100000.0, $rules, 2.5);

        $this->assertSame(0.0, $breakdown['income_tax']);
        $this->assertSame(96800.0, $breakdown['net_salary']);
    }

    public function test_golden_ci_ricf_4_parts_brut_400000(): void
    {
        // Calcul manuel (#2117) : brut 400 000, 4 parts (ex. marié 4 enfants
        //   à charge : base marié 2 + 0,5 × 4 enfants = 4) :
        //   CNSS salariale = 12 800
        //   ITS brut 2024 = 165 000 × 16 % + 160 000 × 21 % = 60 000
        //   RICF (art. 120) = 11 000 × (4 − 1) = 33 000
        //   ITS net = 60 000 − 33 000 = 27 000
        //   Net = 400 000 − 12 800 − 27 000 = 360 200
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(400000.0, $rules, 4.0);

        $this->assertSame(27000.0, $breakdown['income_tax']);
        $this->assertSame(360200.0, $breakdown['net_salary']);
    }

    public function test_golden_ci_ricf_parts_max_brut_3m(): void
    {
        // Calcul manuel (#2117) : brut 3 000 000, 5 parts (plafond légal) :
        //   CNSS salariale = min(3 000 000, 1 647 315) × 3,2 % = 52 714,08
        //     (le plafond retraite 1 647 315 EST atteint — #1913)
        //   ITS brut 2024 = 165 000×16 % + 560 000×21 % + 1 600 000×24 %
        //     + 600 000×28 % = 696 000
        //   RICF (art. 120) = 44 000 (plafond)
        //   ITS net = 696 000 − 44 000 = 652 000
        //   Net = 3 000 000 − 52 714,08 − 652 000 = 2 295 285,92
        $rules = $this->rules();

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(3000000.0, $rules, 5.0);

        $this->assertSame(652000.0, $breakdown['income_tax']);
        $this->assertSame(2295285.92, $breakdown['net_salary']);
    }

    public function test_golden_ci_ricf_default_1_part_no_change(): void
    {
        // Calcul manuel (#2117) : brut 500 000, défaut 1 part (célibataire
        // sans enfant, family_parts null) → RICF 0 → comportement IDENTIQUE
        // au moteur avant #2117 (ITS 81 000, net 403 000 — verrouillé par
        // PayrollCalculationContractTest).
        $rules = $this->rules();

        $this->assertSame(0.0, $rules->familyTaxReduction());

        $breakdown = (new PayrollCalculator)->computeNetBreakdown(500000.0, $rules);

        $this->assertSame(81000.0, $breakdown['income_tax']);
        $this->assertSame(403000.0, $breakdown['net_salary']);
    }
}
