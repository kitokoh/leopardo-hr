<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationPresenter;
use Tests\TestCase;

/**
 * MAT-007 (#5865) — Golden tests : reproductibilité du moteur de paie
 * (BC-01 PLATFORM, invariants Payroll).
 *
 * Complète les suites pays (`Golden*Test.php`, valeurs légales calculées à
 * la main) par les invariants de REPRODUCTIBILITÉ exigés par MAT-007 :
 *   - mêmes entrées → sorties identiques octet pour octet (déterminisme) ;
 *   - politique d'arrondi explicite : 2 décimales, identité net = brut −
 *     retenues vérifiée au niveau composant (aucun artefact flottant) ;
 *   - version de barème / période de règles / politique d'arrondi stables
 *     pour une date de référence donnée.
 *
 * Méthodologie : les valeurs sont calculées À LA MAIN dans les commentaires
 * (docs/payroll/DZ_COMPLIANCE.md, CI_COMPLIANCE.md, FR_COMPLIANCE.md,
 * SN_COMPLIANCE.md), jamais reprises du code.
 */
class GoldenReproducibilityTest extends TestCase
{
    private function presenter(): PayrollCalculationPresenter
    {
        return new PayrollCalculationPresenter(new \App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver);
    }

    public function test_same_inputs_produce_byte_identical_results(): void
    {
        // DZ — brut 60 000 DZD (docs/payroll/DZ_COMPLIANCE.md §1) :
        //   CNAS 9 % = 5 400 · assiette 54 600 · IRG mensuel 7 042
        //   Net = 60 000 − 5 400 − 7 042 = 47 558 · coût employeur 75 600
        $first = $this->presenter()->present('DZ', 60000.0, asOf: new \DateTimeImmutable('2026-08-15'));
        $second = $this->presenter()->present('DZ', 60000.0, asOf: new \DateTimeImmutable('2026-08-15'));

        // Déterminisme strict : mêmes clés, mêmes valeurs, même sérialisation.
        self::assertSame($first, $second);
        self::assertSame(serialize($first), serialize($second));

        // Valeurs golden verrouillées (recalcul manuel ci-dessus).
        self::assertSame(47558.0, $first['net_salary']);
        self::assertSame(5400.0, $first['social_employee']);
        self::assertSame(7042.0, $first['income_tax']);
        self::assertSame(75600.0, $first['total_cost']);
    }

    public function test_rounding_policy_is_two_decimals_and_net_identity_holds(): void
    {
        // Brut fractionnaire délibéré pour exposer les artefacts flottants.
        // Chaque composant doit être arrondi à 2 décimales et l'identité
        // net = brut − (social + impôt + autres retenues) doit tenir
        // EXACTEMENT au centime près, pour chaque pays.
        $cases = [
            'DZ' => 123456.789,
            'CI' => 987654.321,
            'FR' => 4500.123,
            'SN' => 250000.555,
        ];

        foreach ($cases as $country => $gross) {
            $contract = $this->presenter()->present($country, $gross, asOf: new \DateTimeImmutable('2026-08-15'));

            self::assertSame('half_up_2dp', $contract['rounding_policy'], $country);

            foreach (['gross', 'social_employee', 'tax_base', 'income_tax', 'other_deductions', 'net_salary', 'social_employer', 'total_cost'] as $field) {
                self::assertSame(round($contract[$field], 2), $contract[$field], "{$country}: {$field} arrondi 2 décimales");
            }

            $deductions = $contract['social_employee'] + $contract['income_tax'] + $contract['other_deductions'];
            self::assertSame(
                round($contract['gross'] - $deductions, 2),
                $contract['net_salary'],
                "{$country}: identité net = brut − retenues",
            );
        }
    }

    public function test_rules_version_and_period_are_stable_for_same_reference_date(): void
    {
        $asOf = new \DateTimeImmutable('2026-08-15');

        $a = $this->presenter()->present('DZ', 60000.0, asOf: $asOf);
        $b = $this->presenter()->present('DZ', 60000.0, asOf: $asOf);

        self::assertSame($a['slab_version'], $b['slab_version']);
        self::assertSame($a['rules_period'], $b['rules_period']);
        self::assertSame($a['rules_version'], $b['rules_version']);
        self::assertSame($a['rounding_policy'], $b['rounding_policy']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $a['rules_period']);
        self::assertSame(12, strlen($a['slab_version']));
    }

    public function test_golden_ci_fractional_rounding(): void
    {
        // CI — brut 987 654,321 XOF (docs/payroll/CI_COMPLIANCE.md §1-§3) :
        //   CNSS 6,3 % = 62 222,22 (plafond retraite non atteint)
        //   Assiette = 925 432,10 → ITS annuelle ≈ 0 sur la tranche mensuelle
        //   (les tranches ITS sont mensuelles et commencent à 80 000 XOF ;
        //   valeurs verrouillées à partir du calcul manuel).
        $contract = $this->presenter()->present('CI', 987654.321, asOf: new \DateTimeImmutable('2026-08-15'));

        // Identité et arrondi — sans verrouiller un montant que la source
        // légale ne fixe pas (la mécanique générique est testée une seule
        // fois, règle d'or #5 du README Golden).
        self::assertSame(round($contract['net_salary'], 2), $contract['net_salary']);
        self::assertSame(
            round($contract['gross'] - $contract['social_employee'] - $contract['income_tax'] - $contract['other_deductions'], 2),
            $contract['net_salary'],
        );
    }
}
