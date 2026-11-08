<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\AbstractCountryRules;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * #1938 — cas GÉNÉRIQUES du moteur centralisés en UNE seule suite.
 *
 * Prorata (méthode F-05), mécanique horaire des heures supplémentaires et
 * défauts de fin de contrat étaient DUPLIQUÉS À L'IDENTIQUE dans les suites
 * golden BF/ML/GA/CG, avec des valeurs DÉRIVÉES du code
 * (`round(300000 / MONTHLY_HOURS…)`) présentées comme « calculées à la
 * main » — ces cas re-testaient le moteur générique et passeraient même si
 * les taux légaux étaient faux (issue #1938).
 *
 * ⚠️ Ce qui est verrouillé ICI, c'est la MÉCANIQUE du moteur, PAS des
 * valeurs légales. Les valeurs LÉGALES (taux, plafonds, tranches, paliers
 * HS par pays, préavis, indemnités) restent dans les suites golden pays
 * avec leur source citée dans `docs/payroll/*_COMPLIANCE.md` et leur
 * validation experte suivie par #1904/#1912.
 */
class GoldenGenericEngineTest extends TestCase
{
    // ── Prorata (méthode F-05) ───────────────────────────────────────────

    /**
     * @return array<string, array{float, float, float, float}>
     */
    public static function prorataProvider(): array
    {
        // Calculs à la main : base × (jours travaillés / jours ouvrés).
        return [
            'entrée le 15 (12/22)' => [300000.0, 22.0, 12.0, 163636.36],
            'sortie le 10 (7/22)' => [300000.0, 22.0, 7.0, 95454.55],
        ];
    }

    #[DataProvider('prorataProvider')]
    public function test_generic_prorated_base(float $base, float $working, float $actual, float $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator)->computeProratedBase($base, $working, $actual));
    }

    // ── Heures supplémentaires — mécanique horaire générique ─────────────

    /**
     * @return array<string, array{float, float, float, float}>
     */
    public static function overtimeHourlyProvider(): array
    {
        // 300 000 / 173,33 h = 1 730,80 ; attendu en dur.
        // Les paliers LÉGAUX pays (OHADA +15 %, CEMAC +20 %, CI 1,15/1,35/1,50…)
        // sont testés dans les suites pays respectives.
        return [
            '5 h × 1,15 (palier OHADA)' => [300000.0, 5.0, 1.15, 9952.1],
            '5 h × 1,20 (palier CEMAC)' => [300000.0, 5.0, 1.20, 10384.8],
        ];
    }

    #[DataProvider('overtimeHourlyProvider')]
    public function test_generic_overtime_hourly_mechanics(float $base, float $hours, float $multiplier, float $expected): void
    {
        $hourly = round($base / PayrollCalculator::MONTHLY_HOURS, 2);

        $this->assertSame(1730.8, $hourly);
        $this->assertSame($expected, round($hours * $hourly * $multiplier, 2));
    }

    // ── Fin de contrat — DÉFAUTS GÉNÉRIQUES (⚠️ pas des valeurs légales) ──

    public function test_generic_end_of_contract_defaults_are_engine_defaults(): void
    {
        // ⚠️ #1938 : ces assertions verrouillent les DÉFAUTS du moteur
        // (`noticePeriodDays()` → 0 j par défaut, `severanceMonthsPerYear()`
        // → 1,0 mois/an hérité), PAS des valeurs légales pays. Les suites
        // BF/ML/GA/CG verrouillaient auparavant ce 1,0 comme si c'était
        // l'indemnité légale locale (« 1 mois de base × N ans ») — c'est un
        // défaut technique à valider pays par pays (suivi #1904/#1912).
        $rules = new class extends AbstractCountryRules
        {
            protected function defaultTaxSlabs(): array
            {
                return [];
            }

            // Stubs des méthodes abstraites (interface #1868) — le test ne
            // vérifie que les défauts de fin de contrat.
            public function countryCode(): string
            {
                return 'XX';
            }

            public function currency(): string
            {
                return 'XOF';
            }

            public function minimumWage(): float
            {
                return 0.0;
            }

            /** @return list<array<string, mixed>> */
            public function socialContributions(): array
            {
                return [];
            }

            public function calculateIncomeTax(float $grossTaxable, float $annualBasis = 12, ?float $grossForAbatement = null): float
            {
                return 0.0;
            }

            /** @return array{employee: float, employer: float} */
            public function calculateSocialCharges(float $grossSalary): array
            {
                return ['employee' => 0.0, 'employer' => 0.0];
            }

            public function timezone(): string
            {
                return 'UTC';
            }

            /** @return list<int> */
            public function weeklyRestDays(): array
            {
                return [0];
            }

            /** @return list<string> */
            public function supportedPayCycles(): array
            {
                return ['monthly'];
            }

            public function publicHolidaysSource(): string
            {
                return 'none';
            }

            public function confidenceLevel(): string
            {
                return 'placeholder';
            }

            public function language(): string
            {
                return 'fr';
            }

            public function overtimeThresholdWeeklyHours(): float
            {
                return 40.0;
            }

            /** @return list<array{up_to_hours: float|null, multiplier: float}> */
            public function overtimeRateTiers(): array
            {
                return [['up_to_hours' => null, 'multiplier' => 1.0]];
            }
        };

        $this->assertSame(0.0, $rules->noticePeriodDays(5.0));
        $this->assertSame(1.0, $rules->severanceMonthsPerYear(5.0));
    }
}
