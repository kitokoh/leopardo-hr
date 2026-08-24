<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Issue #5244 — matrice golden paie DZ ≥ 40 cas limites (wave W1).
 *
 * Complète les fichiers #5149/#5241 : chaque valeur attendue est CALCULÉE
 * À LA MAIN (docs/payroll/DZ_COMPLIANCE.md §1-§8), jamais reprise du code.
 *
 * Familles couvertes (trous de la matrice existante) :
 *  - bulletins complets (computeNetBreakdown) avec fixtures réalistes :
 *    mi-temps, SMIG, cadres, haut salaire ;
 *  - abattement IRG : plancher / plafond / seuils de tranches intermédiaires ;
 *  - primes intégrées au brut (fixe, % de base) + arrondis composites ;
 *  - congés payés : maintien vs 1/10ᵉ (F-07, valeurs légales DZ) ;
 *  - solde de tout compte : préavis 2 mois (≥ 10 ans), départ en cours de
 *    mois (F-08, valeurs légales DZ).
 *
 * Volontairement SANS base de données (F-13) : AlgeriaPayrollRules retombe
 * sur les barèmes par défaut quand tax_slabs est vide.
 */
class GoldenDzMatrix5244Test extends TestCase
{
    private function calculator(): PayrollCalculator
    {
        return new PayrollCalculator;
    }

    private function rules(): AlgeriaPayrollRules
    {
        return new AlgeriaPayrollRules;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bulletins complets (brut → net → coût employeur) — fixtures réalistes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>
     */
    public static function fullSlipProvider(): array
    {
        // [brut, CNAS salariale, assiette IRG, IRG, net, CNAS patronale, coût employeur]
        return [
            // Mi-temps (base 10 000 DZD) : CNAS 9 % = 900 · assiette 9 100
            // → tranche 0 % → IRG 0 · net 9 100 · patronale 2 600 · coût 12 600.
            'mi-temps 10 000' => [10000.0, 900.0, 9100.0, 0.0, 9100.0, 2600.0, 12600.0],
            // SMIG 20 000 (DZ_COMPLIANCE §3) : CNAS 1 800 · assiette 18 200
            // → tranche 0 % → net 18 200 · patronale 5 200 · coût 25 200.
            'SMIG 20 000' => [20000.0, 1800.0, 18200.0, 0.0, 18200.0, 5200.0, 25200.0],
            // Bas salaire 30 000 : CNAS 2 700 · assiette 27 300 ·
            // IRG 7 300×23 % = 1 679/mois → annuel 20 148 → abattement
            // max(8 059,20 ; 12 000) = PLANCHER 12 000 → (20 148−12 000)/12
            // = 679 · net 26 621 · patronale 7 800 · coût 37 800.
            'bas salaire 30 000 (plancher abattement)' => [30000.0, 2700.0, 27300.0, 679.0, 26621.0, 7800.0, 37800.0],
            // Cadre 40 000 : CNAS 3 600 · assiette 36 400 ·
            // IRG 16 400×23 % = 3 772 → annuel 45 264 → abattement 40 % =
            // 18 105,60 plafonné 18 000 → (45 264−18 000)/12 = 2 272 ·
            // net 34 128 · patronale 10 400 · coût 50 400.
            'cadre 40 000 (plafond abattement)' => [40000.0, 3600.0, 36400.0, 2272.0, 34128.0, 10400.0, 50400.0],
            // Cadre 60 000 (cas canonique doc §1bis) : CNAS 5 400 ·
            // assiette 54 600 · IRG 4 600+14 600×27 % = 8 542 → annuel
            // 102 504 → abattement plafonné 18 000 → (102 504−18 000)/12
            // = 7 042 · net 47 558 · patronale 15 600 · coût 75 600.
            'cadre 60 000' => [60000.0, 5400.0, 54600.0, 7042.0, 47558.0, 15600.0, 75600.0],
            // Cadre supérieur 100 000 : CNAS 9 000 · assiette 91 000 ·
            // IRG 4 600 + 10 800 + 11 000×30 % = 18 700 → annuel 224 400 →
            // abattement plafonné 18 000 → 17 200 · net 73 800 ·
            // patronale 26 000 · coût 126 000.
            'cadre supérieur 100 000' => [100000.0, 9000.0, 91000.0, 17200.0, 73800.0, 26000.0, 126000.0],
            // 120 000 : CNAS 10 800 · assiette 109 200 · IRG 4 600+10 800
            // +29 200×30 % = 24 160 → annuel 289 920 → abattement 18 000 →
            // 22 660 · net 86 540 · patronale 31 200 · coût 151 200.
            'cadre 120 000' => [120000.0, 10800.0, 109200.0, 22660.0, 86540.0, 31200.0, 151200.0],
            // Haut salaire 250 000 : CNAS 22 500 · assiette 227 500 ·
            // IRG 4 600+10 800+24 000+67 500×33 % = 61 675 → annuel 740 100
            // → abattement 18 000 → 60 175 · net 167 325 ·
            // patronale 65 000 · coût 315 000.
            'haut salaire 250 000' => [250000.0, 22500.0, 227500.0, 60175.0, 167325.0, 65000.0, 315000.0],
            // Tranche 35 % : 500 000 → CNAS 45 000 · assiette 455 000 ·
            // IRG 4 600+10 800+24 000+52 800+135 000×35 % = 139 450 →
            // annuel 1 673 400 → abattement 18 000 → 137 950 ·
            // net 317 050 · patronale 130 000 · coût 630 000.
            'tranche 35 % 500 000' => [500000.0, 45000.0, 455000.0, 137950.0, 317050.0, 130000.0, 630000.0],
        ];
    }

    #[DataProvider('fullSlipProvider')]
    public function test_golden_dz_full_slip_matrix(
        float $gross,
        float $expectedEmployee,
        float $expectedTaxable,
        float $expectedIrg,
        float $expectedNet,
        float $expectedEmployer,
        float $expectedCost
    ): void {
        $breakdown = $this->calculator()->computeNetBreakdown($gross, $this->rules());

        $this->assertSame($expectedEmployee, $breakdown['social']['employee']);
        $this->assertSame($expectedTaxable, $breakdown['taxable_gross']);
        $this->assertSame($expectedIrg, $breakdown['income_tax']);
        $this->assertSame($expectedNet, $breakdown['net_salary']);
        $this->assertSame($expectedEmployer, $breakdown['social']['employer']);
        $this->assertSame($expectedCost, $breakdown['total_cost']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // IRG — seuils de tranches et abattement (assiette imposable directe)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    public static function irgAbatementProvider(): array
    {
        // [assiette imposable, IRG mensuel] — calculs manuels §1/§1bis.
        return [
            // 18 000 : tranche 0 % → IRG 0 (sous SMIG imposable).
            '18 000 (tranche 0 %)' => [18000.0, 0.0],
            // 22 000 : 2 000×23 % = 460 → annuel 5 520 → abattement
            // max(2 208 ; 12 000) = 12 000 > impôt annuel → IRG 0
            // (le plancher d'abattement efface l'impôt).
            '22 000 (plancher efface l impôt)' => [22000.0, 0.0],
            // 25 000 : 5 000×23 % = 1 150 → annuel 13 800 → abattement
            // max(5 520 ; 12 000) = PLANCHER → (13 800−12 000)/12 = 150.
            '25 000 (plancher abattement)' => [25000.0, 150.0],
            // 35 000 : 15 000×23 % = 3 450 → annuel 41 400 → abattement
            // max(16 560 ; 12 000) = 16 560 → (41 400−16 560)/12 = 2 070.
            '35 000 (abattement 40 % sous plafond)' => [35000.0, 2070.0],
            // 45 000 : 4 600 + 5 000×27 % = 5 950 → annuel 71 400 →
            // abattement plafonné 18 000 → (71 400−18 000)/12 = 4 450.
            '45 000 (plafond abattement)' => [45000.0, 4450.0],
            // 55 000 : 4 600 + 15 000×27 % = 8 650 → annuel 103 800 →
            // abattement 18 000 → (103 800−18 000)/12 = 7 150.
            '55 000' => [55000.0, 7150.0],
            // 90 000 : 4 600+10 800+10 000×30 % = 18 400 → annuel 220 800 →
            // abattement 18 000 → (220 800−18 000)/12 = 16 900.
            '90 000 (tranche 30 %)' => [90000.0, 16900.0],
            // 130 000 : 4 600+10 800+50 000×30 % = 30 400 → annuel 364 800 →
            // abattement 18 000 → (364 800−18 000)/12 = 28 900.
            '130 000' => [130000.0, 28900.0],
            // 200 000 : 4 600+10 800+24 000+40 000×33 % = 52 600 →
            // annuel 631 200 → abattement 18 000 → 51 100.
            '200 000 (tranche 33 %)' => [200000.0, 51100.0],
            // 300 000 : 4 600+10 800+24 000+140 000×33 % = 85 600 →
            // annuel 1 027 200 → abattement 18 000 → 84 100.
            '300 000' => [300000.0, 84100.0],
        ];
    }

    #[DataProvider('irgAbatementProvider')]
    public function test_golden_dz_irg_abatement_matrix(float $taxable, float $expectedIrg): void
    {
        $this->assertSame($expectedIrg, $this->rules()->calculateIncomeTax($taxable));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Primes intégrées au brut (soumises) + arrondis composites
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>
     */
    public static function primesProvider(): array
    {
        // [brut (base + prime), CNAS salariale, assiette IRG, IRG, net, patronale, coût]
        return [
            // Base 60 000 + prime fixe 10 000 (brut 70 000) : CNAS 6 300 ·
            // assiette 63 700 · IRG 4 600+23 700×27 % = 10 999 → annuel
            // 131 988 → abattement 18 000 → 9 499 · net 54 201 ·
            // patronale 18 200 · coût 88 200 (recoupe le golden DB #5149).
            'prime fixe 10 000 (soumise)' => [70000.0, 6300.0, 63700.0, 9499.0, 54201.0, 18200.0, 88200.0],
            // Base 60 000 + prime fixe 5 000 (brut 65 000) : CNAS 5 850 ·
            // assiette 59 150 · IRG 4 600+19 150×27 % = 9 770,50 → annuel
            // 117 246 → abattement 18 000 → 8 270,50 · net 50 879,50 ·
            // patronale 16 900 · coût 81 900.
            'prime fixe 5 000 (soumise)' => [65000.0, 5850.0, 59150.0, 8270.5, 50879.5, 16900.0, 81900.0],
            // Base 60 000 + prime 10 % de la base (brut 66 000) : CNAS 5 940 ·
            // assiette 60 060 · IRG 4 600+20 060×27 % = 10 016,20 → annuel
            // 120 194,40 → abattement 18 000 → 8 516,20 · net 51 543,80 ·
            // patronale 17 160 · coût 83 160.
            'prime 10 % de base' => [66000.0, 5940.0, 60060.0, 8516.2, 51543.8, 17160.0, 83160.0],
            // Arrondi composite : base 33 333,33 + prime 1 000,67 = brut
            // 34 334 · CNAS 3 090,06 · assiette 31 243,94 · IRG 11 243,94
            // ×23 % (tranche 20-40k) = 2 586,1062 → annuel 31 033,2744 →
            // abattement 40 % = 12 413,31 (sous plafond) → (31 033,2744 −
            // 12 413,3098)/12 = 1 551,66 · net 29 692,28 · patronale
            // 8 926,84 · coût 43 260,84.
            'arrondi composite 34 334' => [34334.0, 3090.06, 31243.94, 1551.66, 29692.28, 8926.84, 43260.84],
        ];
    }

    #[DataProvider('primesProvider')]
    public function test_golden_dz_primes_matrix(
        float $gross,
        float $expectedEmployee,
        float $expectedTaxable,
        float $expectedIrg,
        float $expectedNet,
        float $expectedEmployer,
        float $expectedCost
    ): void {
        $breakdown = $this->calculator()->computeNetBreakdown($gross, $this->rules());

        $this->assertSame($expectedEmployee, $breakdown['social']['employee']);
        $this->assertSame($expectedTaxable, $breakdown['taxable_gross']);
        $this->assertSame($expectedIrg, $breakdown['income_tax']);
        $this->assertSame($expectedNet, $breakdown['net_salary']);
        $this->assertSame($expectedEmployer, $breakdown['social']['employer']);
        $this->assertSame($expectedCost, $breakdown['total_cost']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Congés payés — maintien de salaire vs règle du 1/10ᵉ (F-07)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}>
     */
    public static function leaveIndemnityProvider(): array
    {
        // [base, jours pris, jours ouvrés, référentiel 12 mois, congés acquis, indemnité]
        return [
            // 5 j : maintien 60 000×5/22 = 13 636,36 > 1/10ᵉ 72 000×5/30
            // = 12 000 → 13 636,36 (cas doc §6).
            '5 j — maintien gagne' => [60000.0, 5.0, 22.0, 720000.0, 30.0, 13636.36],
            // 10 j avec augmentation (réf. 900 000) : maintien 27 272,73
            // < 1/10ᵉ 90 000×10/30 = 30 000 → 30 000,00 (cas doc §6).
            '10 j — 1/10ᵉ gagne' => [60000.0, 10.0, 22.0, 900000.0, 30.0, 30000.0],
            // Mois complet (22 j) : maintien 60 000 > 1/10ᵉ 52 800 → 60 000.
            'mois complet 22 j' => [60000.0, 22.0, 22.0, 720000.0, 30.0, 60000.0],
            // 2 j : maintien 5 454,55 > 1/10ᵉ 4 800 → 5 454,55.
            '2 j — maintien gagne' => [60000.0, 2.0, 22.0, 720000.0, 30.0, 5454.55],
            // 7 j : maintien 60 000×7/22 = 19 090,91 > 1/10ᵉ 16 800
            // → 19 090,91.
            '7 j — maintien gagne' => [60000.0, 7.0, 22.0, 720000.0, 30.0, 19090.91],
            // 15 j, base 100 000, réf. 1 400 000 : maintien 68 181,82
            // < 1/10ᵉ 140 000×15/30 = 70 000 → 70 000,00.
            '15 j — 1/10ᵉ gagne (base 100 000)' => [100000.0, 15.0, 22.0, 1400000.0, 30.0, 70000.0],
            // Congés acquis complets (30 j) : maintien 81 818,18
            // > 1/10ᵉ 72 000 → 81 818,18.
            '30 j — maintien gagne' => [60000.0, 30.0, 22.0, 720000.0, 30.0, 81818.18],
            // Aucun jour pris → 0.
            '0 j — aucune indemnité' => [60000.0, 0.0, 22.0, 720000.0, 30.0, 0.0],
        ];
    }

    #[DataProvider('leaveIndemnityProvider')]
    public function test_golden_dz_leave_indemnity_matrix(
        float $base,
        float $leaveDays,
        float $workingDays,
        float $ref12,
        float $accrued,
        float $expected
    ): void {
        $this->assertSame(
            $expected,
            $this->calculator()->computeLeaveIndemnity($base, $leaveDays, $workingDays, $ref12, $accrued)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Solde de tout compte (F-08) — valeurs légales DZ
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float, 7: float}>
     */
    public static function settlementProvider(): array
    {
        // [base, années, jours travaillés, jours ouvrés, congés non pris, réf. 12 mois, préavis (j ouvrés), total]
        return [
            // CDI 10 ans et + : préavis 2 mois = 44 j OUVRÉS
            // (AlgeriaPayrollRules::noticePeriodDays ≥ 10 ans, #1943).
            // Prorata 60 000 · congés max(13 636,36 ; 12 000) = 13 636,36 ·
            // préavis 60 000×44/22 = 120 000 · licenciement 60 000×10
            // = 600 000 → total 793 636,36.
            '10 ans, préavis 44 j ouvrés' => [60000.0, 10.0, 22.0, 22.0, 5.0, 720000.0, 44.0, 793636.36],
            // Départ en cours de mois (15 j travaillés), 1 an, sans congés :
            // prorata 60 000×15/22 = 40 909,09 · licenciement 60 000
            // → total 100 909,09.
            'départ mi-mois, 1 an' => [60000.0, 1.0, 15.0, 22.0, 0.0, 720000.0, 0.0, 100909.09],
            // CDD 2 ans, mois complet, 10 j congés non pris :
            // congés max(27 272,73 ; 24 000) = 27 272,73 · licenciement
            // 60 000×2 = 120 000 → total 207 272,73.
            'CDD 2 ans, 10 j congés non pris' => [60000.0, 2.0, 22.0, 22.0, 10.0, 720000.0, 0.0, 207272.73],
            // Sortie immédiate après 6 mois, congés non pris 3 j :
            // prorata 60 000 · congés max(8 181,82 ; 7 200) = 8 181,82 ·
            // licenciement 60 000×0,5 = 30 000 → total 98 181,82.
            '6 mois, 3 j congés non pris' => [60000.0, 0.5, 22.0, 22.0, 3.0, 720000.0, 0.0, 98181.82],
        ];
    }

    #[DataProvider('settlementProvider')]
    public function test_golden_dz_settlement_matrix(
        float $base,
        float $years,
        float $proratedDays,
        float $workingDays,
        float $leaveDays,
        float $ref12,
        float $noticeDays,
        float $expectedTotal
    ): void {
        $result = $this->calculator()->computeFinalSettlement(
            $base,
            $years,
            $proratedDays,
            $workingDays,
            $leaveDays,
            $ref12,
            1.0,
            $noticeDays
        );

        $this->assertSame($expectedTotal, $result['total']);
    }
}
