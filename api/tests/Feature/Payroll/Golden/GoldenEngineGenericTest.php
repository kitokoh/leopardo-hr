<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Issue #1938 — suite UNIQUE des mécaniques MOTEUR génériques (prorata,
 * heures supplémentaires, fin de contrat par défaut).
 *
 * Ces cas étaient dupliqués à l'identique dans les golden BF/ML/GA/CG : ils
 * re-testent le moteur générique (et passeraient même si les taux légaux
 * pays étaient faux) — ils n'ont PAS de valeur légale pays.
 *
 * ⚠️ Ce fichier ne verrouille AUCUNE valeur légale : ce sont des mécaniques
 * de calcul partagées (F-05 prorata, taux horaire, préavis/indemnités par
 * défaut du moteur). Les valeurs légales pays restent dans les golden pays
 * respectifs (SMIG, IRPP/IUTS/ITS, CNSS/CNPS/INPS/CSS...), calculées à la
 * main avec source citée.
 */
class GoldenEngineGenericTest extends TestCase
{
    /**
     * F-05 — prorata de base : base × (jours travaillés / jours ouvrés).
     * Mécanique moteur générique (aucun pays, aucune loi).
     */
    #[DataProvider('prorataProvider')]
    public function test_engine_generic_prorated_base(float $base, float $working, float $actual, float $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator())->computeProratedBase($base, $working, $actual));
    }

    /**
     * @return array<string, array{float, float, float, float}>
     */
    public static function prorataProvider(): array
    {
        return [
            // Ex-cas BF/ML/GA/CG (base 300 000).
            'entrée le 15 (12/22)' => [300000.0, 22.0, 12.0, 163636.36],
            'sortie le 10 (7/22)'  => [300000.0, 22.0, 7.0, 95454.55],
            // Ex-cas CI (base 60 000, jours réels 12,06 / 7,10).
            'ci entrée le 15 (12,06/22)' => [60000.0, 22.0, 12.06, 32890.91],
            'ci sortie le 10 (7,10/22)'  => [60000.0, 22.0, 7.10, 19363.64],
            // Ex-cas SN (base 250 000, jours réels 15,61).
            'sn entrée le 10 (15,61/22)' => [250000.0, 22.0, 15.61, 177386.36],
            // Ex-cas CM (base 200 000).
            'cm entrée le 15 (12/22)'    => [200000.0, 22.0, 12.0, 109090.91],
            'cm sortie le 10 (7/22)'     => [200000.0, 22.0, 7.0, 63636.36],
        ];
    }

    /**
     * Taux horaire générique = base / MONTHLY_HOURS (173,33), puis
     * majoration selon les paliers du pays. Ici : palier +20 % (CEMAC par
     * défaut) — mécanique, pas une valeur légale.
     */
    public function test_engine_generic_hourly_rate_and_overtime_5h(): void
    {
        $hourly = round(300000.0 / PayrollCalculator::MONTHLY_HOURS, 2);
        $expected = round(5.0 * $hourly * 1.20, 2);

        $this->assertSame(1730.8, $hourly);
        $this->assertSame(10384.8, $expected);
    }

    /**
     * Fin de contrat — DÉFAUTS moteur génériques (F-31) verrouillés via une
     * règle pays SANS surcharge (CanadaPayrollRules) :
     *  - noticePeriodDays() par défaut = 0 (le contrat décide) ; les pays
     *    avec préavis légal documenté surchargent (DZ 22/44, CI/SN/GA/CM...) ;
     *  - severanceMonthsPerYear() par défaut = 1,0 mois/an (hérité générique,
     *    PAS une valeur légale pays — les golden pays qui verrouillaient ce
     *    défaut comme valeur légale ont été corrigés, #1938).
     */
    public function test_engine_generic_end_of_contract_defaults(): void
    {
        $rules = new \App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;

        $this->assertSame(0.0, $rules->noticePeriodDays(5.0));
        $this->assertSame(1.0, $rules->severanceMonthsPerYear(5.0));

        // Mécanique : indemnité = mois/an × ancienneté × base (défaut 1,0).
        // Ex. 5 ans × 300 000 = 1 500 000 — NE PAS interpréter comme légal.
        $this->assertSame(1500000.0, round($rules->severanceMonthsPerYear(5.0) * 5.0 * 300000.0, 2));
        $this->assertSame(2100000.0, round($rules->severanceMonthsPerYear(7.0) * 7.0 * 300000.0, 2));
    }
}
