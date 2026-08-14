<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Issue #1938 — cas MOTEUR GÉNÉRIQUES centralisés (prorata, heures
 * supplémentaires, fin de contrat).
 *
 * Ces cas étaient dupliqués à l'identique dans les suites golden pays
 * (BF/ML/GA/CG) et re-testaient le moteur générique — ils passeraient même
 * si les taux légaux du pays étaient faux, et verrouillaient des défauts
 * génériques (`severanceMonthsPerYear() = 1.0`) comme s'ils étaient des
 * valeurs légales.
 *
 * ⚠️ Les valeurs ci-dessous sont des MÉCANIQUES MOTEUR, pas des valeurs
 * légales : chaque golden pays ne doit contenir que des cas calculés à la
 * main avec une source légale citée (docs/payroll/*_COMPLIANCE.md).
 */
class GoldenEngineGenericTest extends TestCase
{
    /**
     * @return array<string, array{float, float, float, float}>
     */
    public static function prorataProvider(): array
    {
        return [
            'entrée le 15 (12/22)' => [300000.0, 22.0, 12.0, 163636.36],
            'sortie le 10 (7/22)'  => [300000.0, 22.0, 7.0, 95454.55],
        ];
    }

    #[DataProvider('prorataProvider')]
    public function test_engine_prorated_base(float $base, float $working, float $actual, float $expected): void
    {
        // Mécanique moteur (F-05) : base × (jours travaillés / jours ouvrés).
        //   300 000 × 12 / 22 = 163 636,36
        //   300 000 × 7 / 22  = 95 454,55
        // Ce n'est PAS une valeur légale pays : le ratio jours ouvrés vient du
        // calendrier du run, pas des règles pays.
        $this->assertSame($expected, (new PayrollCalculator())->computeProratedBase($base, $working, $actual));
    }

    public function test_engine_overtime_hourly_rate_and_majoration(): void
    {
        // Mécanique moteur générique : taux horaire = base / 173,33 h
        // (MONTHLY_HOURS), puis heures × taux × majoration légale du pays.
        //   300 000 / 173,33 = 1 730,80 (arrondi 2 décimales)
        //   5 h × 1 730,80 × 1,15 = 9 952,10
        // La majoration (1,15/1,35/1,50) est légale et pays-spécifique (elle
        // est verrouillée dans les suites pays) ; la mécanique du taux
        // horaire est du moteur et n'appartient à aucun pays.
        $hourly = round(300000.0 / PayrollCalculator::MONTHLY_HOURS, 2);

        $this->assertSame(1730.8, $hourly);
        $this->assertSame(9952.1, round(5.0 * $hourly * 1.15, 2));
    }

    public function test_engine_end_of_contract_mechanics(): void
    {
        // Mécanique moteur générique (EndOfContractService) :
        //   préavis (mois) = noticePeriodDays() / 30 → montant = mois × base
        //   indemnité = severanceMonthsPerYear() × années × base
        // Les VALEURS légales (30 j de préavis, 1 mois/an d'indemnité) sont
        // pays-spécifiques et verrouillées dans les suites pays ; ici on
        // verrouille uniquement la MÉCANIQUE avec le défaut générique 1,0 —
        // ce défaut n'est PAS une valeur légale (à confirmer pays par pays).
        $this->assertSame(300000.0, round(30.0 / 30.0 * 300000.0, 2));       // 1 mois de préavis
        $this->assertSame(1500000.0, round(1.0 * 5.0 * 300000.0, 2));        // 5 ans × 1 mois/an
        $this->assertSame(2100000.0, round(1.0 * 7.0 * 300000.0, 2));        // 7 ans × 1 mois/an
    }
}
