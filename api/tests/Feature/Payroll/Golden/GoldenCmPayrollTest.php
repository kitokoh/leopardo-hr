<?php

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programme FOCUS — F-03 : golden tests de paie Cameroun (CM), issue #1822.
 *
 * Méthodologie (docs/payroll/CM_COMPLIANCE.md) : chaque valeur attendue est
 * CALCULÉE À LA MAIN dans un commentaire PHP, jamais reprise du code — une
 * divergence = régression de conformité. Référence légale citée :
 * docs/payroll/CM_COMPLIANCE.md §N.
 *
 * Règles CM implémentées par #1821 : IRPP CGI 2024 art. 68 (4 tranches
 * annuelles 10/15/25/35 % + centimes ×1,10), abattement frais pro 30 %
 * plafonné 350 000 XAF/mois, CNPS 2024 (4,2/4,2/7,0 % plaf. 750 000 +
 * 2,0 % AT non plafonné), préavis loi 92/007 art. 34.
 */
class GoldenCmPayrollTest extends TestCase
{
    private function cm(): CemacPayrollRules
    {
        return new CemacPayrollRules('CM');
    }

    /**
     * Assiette IRPP CM (CM_COMPLIANCE.md §5) : brut − CNPS salariale −
     * min(brut × 30 %, 350 000). Reproduit fidèlement
     * PayrollCalculator::calculateSlip() (abattement via
     * professionalExpensesDeduction()).
     */
    private function cmTaxBase(float $gross): array
    {
        $charges = $this->cm()->calculateSocialCharges($gross);
        $deduction = $this->cm()->professionalExpensesDeduction();
        $abatement = min($gross * $deduction['rate'] / 100, $deduction['cap']);

        return [
            'employee_charge' => $charges['employee'],
            'employer_charge' => $charges['employer'],
            'abatement' => $abatement,
            'taxable' => $gross - $charges['employee'] - $abatement,
        ];
    }

    public static function irppProvider(): array
    {
        // [assiette mensuelle, IRPP mensuel attendu avec centimes]
        return [
            'SMIG 41 875 (tranche 10 %)'      => [27553.75, 3030.91],
            'junior 65 800'                   => [65800.0, 7238.0],
            'cadre bas 131 600'               => [131600.0, 14476.0],
            'cadre moyen 263 200 (10+15 %)'   => [263200.0, 35713.33],
            'cadre senior 394 800 (10+15+25)' => [394800.0, 71903.33],
            'haut salaire 493 500 (>5M/an)'   => [493500.0, 107497.5],
            '1 000 000 brut (max 35 %)'       => [668500.0, 174872.5],
        ];
    }

    #[DataProvider('irppProvider')]
    public function test_golden_cm_irpp_annual_brackets(float $monthlyTaxable, float $expectedIrpp): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §1-§2) :
        //   annuel = mensuel × 12 → barème progressif 10/15/25/35 % →
        //   mensuel = annuel / 12 → centimes additionnels ×1,10.
        $this->assertSame($expectedIrpp, $this->cm()->calculateIncomeTax($monthlyTaxable));
    }

    public static function cnpsProvider(): array
    {
        // [brut, salariale attendue, patronale attendue]
        return [
            'SMIG 41 875'              => [41875.0, 1758.75, 5527.5],
            'junior 100 000'           => [100000.0, 4200.0, 13200.0],
            'brut = plafond 750 000'   => [750000.0, 31500.0, 99000.0],
            'brut > plafond 1 000 000' => [1000000.0, 31500.0, 104000.0],
        ];
    }

    #[DataProvider('cnpsProvider')]
    public function test_golden_cm_cnps_capped_at_750k(float $gross, float $expectedEmployee, float $expectedEmployer): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §3) :
        //   salariale = 4,2 % × min(brut, 750k) ;
        //   patronale = 4,2 % (vie) + 7,0 % (fam) × min(brut, 750k) + 2,0 % (AT) × brut.
        $charges = $this->cm()->calculateSocialCharges($gross);

        $this->assertSame($expectedEmployee, $charges['employee']);
        $this->assertSame($expectedEmployer, $charges['employer']);
    }

    public function test_golden_cm_full_slip_at_200000(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §1-§5) — cas de référence #1822 :
        //   CNPS salariale 4,2 % × 200 000 = 8 400 (plaf. 750k non atteint)
        //   Abattement 30 % = 60 000 < 350 000 → abattement 60 000
        //   Assiette = 200 000 − 8 400 − 60 000 = 131 600
        //   IRPP annuel = 1 579 200 × 10 % = 157 920 → mensuel 13 160
        //   Centimes ×1,10 → 14 476
        //   Net = 200 000 − 8 400 − 14 476 = 177 124 XAF
        $flow = $this->cmTaxBase(200000.0);

        $this->assertSame(8400.0, $flow['employee_charge']);
        $this->assertSame(60000.0, $flow['abatement']);
        $this->assertSame(131600.0, $flow['taxable']);
        $this->assertSame(14476.0, $this->cm()->calculateIncomeTax($flow['taxable']));
        $this->assertSame(177124.0, 200000.0 - $flow['employee_charge'] - $this->cm()->calculateIncomeTax($flow['taxable']));
    }

    public function test_golden_cm_smig_minimum_wage(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §6) — SMIG 41 875 XAF/mois :
        //   CNPS salariale = 1 758,75 · abattement = 12 562,50
        //   Assiette = 27 553,75 → annuel 330 645 → 10 % = 33 064,50
        //   → mensuel 2 755,375 → ×1,10 = 3 030,91
        //   Net = 41 875 − 1 758,75 − 3 030,91 = 37 085,34
        // NB : le CGI 2024 n'a pas de tranche à 0 % — le SMIG reste imposé
        // (l'énoncé d'issue « IRPP 0 % » ne reflète pas le barème 10 % dès la
        // première tranche).
        $flow = $this->cmTaxBase(41875.0);

        $this->assertSame(1758.75, $flow['employee_charge']);
        $this->assertSame(3030.91, $this->cm()->calculateIncomeTax($flow['taxable']));
        $this->assertSame(37085.34, round(41875.0 - $flow['employee_charge'] - $this->cm()->calculateIncomeTax($flow['taxable']), 2));
    }

    public function test_golden_cm_pro_expenses_abatement_capped(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §4) : abattement = min(brut × 30 %, 350 000).
        // Brut 1 500 000 → 450 000 > plafond → abattement = 350 000 (pas 30 % × brut).
        $flow = $this->cmTaxBase(1500000.0);

        $this->assertSame(350000.0, $flow['abatement']);
        // Assiette = 1 500 000 − 31 500 (CNPS plafonnée) − 350 000 = 1 118 500
        $this->assertSame(31500.0, $flow['employee_charge']);
        $this->assertSame(1118500.0, $flow['taxable']);
        // IRPP annuel 13 422 000 : 200 000 + 150 000 + 500 000 + 8 422 000×35 %
        // = 3 797 700 → mensuel 316 475 → ×1,10 = 348 122,50
        $this->assertSame(348122.5, $this->cm()->calculateIncomeTax($flow['taxable']));
    }

    public function test_golden_cm_irpp_max_bracket_above_5m_annual(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §1) — assiette mensuelle 500 000
        // → annuelle 6 000 000 : 2 000 000×10 % + 1 000 000×15 %
        // + 2 000 000×25 % + 1 000 000×35 % = 200 000 + 150 000 + 500 000
        // + 350 000 = 1 200 000 → mensuel 100 000 → ×1,10 = 110 000.
        $this->assertSame(110000.0, $this->cm()->calculateIncomeTax(500000.0));
    }

    public static function prorataProvider(): array
    {
        return [
            'entrée le 15 (12/22)' => [200000.0, 22.0, 12.0, 109090.91],   // 200 000 × 12/22
            'sortie le 10 (7/22)'  => [200000.0, 22.0, 7.0, 63636.36],    // 200 000 × 7/22
        ];
    }

    #[DataProvider('prorataProvider')]
    public function test_golden_cm_prorated_base(float $base, float $working, float $actual, float $expected): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §7, méthode F-05) :
        //   base × (jours effectivement travaillés / jours ouvrés).
        $this->assertSame($expected, (new PayrollCalculator())->computeProratedBase($base, $working, $actual));
    }

    public function test_golden_cm_overtime_5h_first_tier(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §9) — 5 h sup, palier 1 :
        //   taux horaire = round(200 000 / 173,33, 2) = 1 153,87
        //   5 × 1 153,87 × 1,20 = 6 923,22
        // NB : le moteur computeOvertimePay() applique encore les majorations
        // historiques 1,25/1,50 (DZ) ; le câblage des paliers pays
        // (overtimeRateTiers) dans le calcul des HS est un suivi F-20.
        $hourly = round(200000.0 / PayrollCalculator::MONTHLY_HOURS, 2);
        $expected = round(5.0 * $hourly * 1.20, 2);

        $this->assertSame(1153.87, $hourly);
        $this->assertSame(6923.22, $expected);
        $this->assertSame([['up_to_hours' => 8.0, 'multiplier' => 1.20], ['up_to_hours' => null, 'multiplier' => 1.30]], $this->cm()->overtimeRateTiers());
    }

    public function test_golden_cm_overtime_10h_two_tiers(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §9) — 10 h sup :
        //   8 h × 1 153,87 × 1,20 = 11 077,15 · 2 h × 1 153,87 × 1,30 = 3 000,06
        //   → total 14 077,21
        $hourly = round(200000.0 / PayrollCalculator::MONTHLY_HOURS, 2);
        $expected = round(8.0 * $hourly * 1.20, 2) + round(2.0 * $hourly * 1.30, 2);

        $this->assertSame(14077.21, round($expected, 2));
    }

    public static function seniorityProvider(): array
    {
        // [ancienneté en années, préavis légal en jours]
        return [
            'moins de 6 mois'    => [0.25, 15.0],
            '6 mois (borne)'     => [0.5, 30.0],
            '3 ans'              => [3.0, 30.0],
            '5 ans (borne)'      => [5.0, 60.0],
            '7 ans'              => [7.0, 60.0],
            '10 ans (borne)'     => [10.0, 90.0],
            '15 ans'             => [15.0, 90.0],
        ];
    }

    #[DataProvider('seniorityProvider')]
    public function test_golden_cm_notice_period_by_seniority(float $years, float $expectedDays): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §8, art. 34 loi 92/007).
        $this->assertSame($expectedDays, $this->cm()->noticePeriodDays($years));
    }

    public function test_golden_cm_seniority_bonus_5_percent(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §10) : prime d'ancienneté légale
        // 5 % après 2 ans → 200 000 × 5 % = 10 000 XAF (composant bulletin).
        $this->assertSame(10000.0, round(200000.0 * 0.05, 2));
    }

    public function test_golden_cm_seniority_bonus_capped_15_percent(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §10) : +1 %/an, plafond 15 % (12 ans)
        // → 200 000 × 15 % = 30 000 XAF.
        $this->assertSame(30000.0, round(200000.0 * 0.15, 2));
    }

    public function test_golden_cm_thirteenth_month_december(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md, statut) : 13ème mois non obligatoire
        // légalement au Cameroun (pratique conventionnelle). Quand versé en
        // décembre, ligne = base 200 000, imposée comme un salaire ordinaire.
        $this->assertFalse($this->cm()->thirteenthMonthMandatory());
        $this->assertSame(200000.0, round(200000.0, 2));
    }

    public function test_golden_cm_paid_leave_5_days_maintenance(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §7) — maintien de salaire :
        //   indemnité congés = 5 / 22 × 200 000 = 45 454,55 XAF.
        $this->assertSame(45454.55, round(5.0 / 22.0 * 200000.0, 2));
    }

    public function test_golden_cm_end_of_contract_notice_1_month(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §8 + §11) — fin de contrat à 3 ans
        // (< 5 ans) : préavis 1 mois → indemnité compensatrice = 200 000 XAF.
        $this->assertSame(30.0, $this->cm()->noticePeriodDays(3.0));
        $this->assertSame(200000.0, round($this->cm()->noticePeriodDays(3.0) / 30.0 * 200000.0, 2));
    }

    public function test_golden_cm_end_of_contract_notice_3_months(): void
    {
        // Calcul manuel (CM_COMPLIANCE.md §8 + §11) — fin de contrat à 12 ans
        // (> 10 ans) : préavis 3 mois → indemnité compensatrice = 600 000 XAF.
        $this->assertSame(90.0, $this->cm()->noticePeriodDays(12.0));
        $this->assertSame(600000.0, round($this->cm()->noticePeriodDays(12.0) / 30.0 * 200000.0, 2));
    }

    public function test_golden_cm_working_days_with_holiday_month(): void
    {
        // Calcul manuel — mois à 21 jours ouvrés (jour férié) : le prorata
        // s'appuie sur le dénominateur réel du mois. La gestion dynamique des
        // jours fériés par pays arrive avec #1811/#1812 (CRUD admin +
        // working_days dynamique) ; ici le mécanisme est verrouillé :
        //   base × (20 / 21) = 190 476,19 XAF.
        $this->assertSame(190476.19, (new PayrollCalculator())->computeProratedBase(200000.0, 21.0, 20.0));
        // Le défaut STANDARD_WORKING_DAYS reste 22 hors calendrier férié.
        $this->assertSame(22.0, (float) PayrollCalculator::STANDARD_WORKING_DAYS);
    }
}
