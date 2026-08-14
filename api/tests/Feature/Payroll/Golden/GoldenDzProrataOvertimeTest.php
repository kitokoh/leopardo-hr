<?php

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-05 : golden tests prorata, heures supplémentaires,
 * absences (méthodes pures de PayrollCalculator).
 *
 * Méthode : valeur en dur calculée à la main — référence
 * docs/payroll/DZ_COMPLIANCE.md §5. Méthode de prorata : jours travaillés
 * (actual_days_worked / 22), recoupe contrat ↔ période du run.
 *
 * Base de données : nécessaire depuis F-20 (#1816) — computeWorkedDays()
 * interroge les logs de présence réels (AttendanceLog) avant de retomber sur
 * le prorata contrat ; les modèles des cas purs ne sont pas persistés
 * (casts date/Carbon) et le fallback est vérifié sans aucun log.
 */
class GoldenDzProrataOvertimeTest extends TestCase
{
    use RefreshTenantDatabase;
    public static function prorataProvider(): array
    {
        return [
            'mois complet'           => [60000.0, 22.0, 22.0, 60000.0],
            'absence 1 jour (21/22)' => [60000.0, 22.0, 21.0, 57272.73],   // retenue 2 727,27
            'congés sans solde 5 j'  => [60000.0, 22.0, 17.0, 46363.64],   // retenue 13 636,36
            'entrée 15/07 (12,06/22)'=> [60000.0, 22.0, 12.06, 32890.91],
            'zéro jour'              => [60000.0, 22.0, 0.0, 0.0],
        ];
    }

    #[DataProvider('prorataProvider')]
    public function test_golden_dz_prorated_base(float $base, float $working, float $actual, float $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator())->computeProratedBase($base, $working, $actual));
    }

    public static function overtimeProvider(): array
    {
        return [
            'zéro heure'             => [60000.0, 0.0, 0.0],
            '10 h à 25 % (seuil)'    => [60000.0, 10.0, 4327.0],
            '10 h 25 % + 5 h 50 %'   => [60000.0, 15.0, 6923.2],
            '11 h (1 h à 50 %)'      => [60000.0, 11.0, 4846.24],
        ];
    }

    #[DataProvider('overtimeProvider')]
    public function test_golden_dz_overtime_pay(float $base, float $hours, float $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator())->computeOvertimePay($base, $hours));
    }

    public static function workedDaysProvider(): array
    {
        // [contrat_start, contrat_end, period_start, period_end, expected_actual]
        return [
            'plein mois (01→31/07)'       => ['2026-07-01', null, '2026-07-01', '2026-07-31', 22.0],
            'entrée 15/07 (17 j sur 31)'  => ['2026-07-15', null, '2026-07-01', '2026-07-31', 12.06],
            'sortie 10/07 (10 j sur 31)'  => ['2026-07-01', '2026-07-10', '2026-07-01', '2026-07-31', 7.1],
            'embauché en août (hors période)' => ['2026-08-01', null, '2026-07-01', '2026-07-31', 0.0],
        ];
    }

    #[DataProvider('workedDaysProvider')]
    public function test_golden_dz_worked_days(
        ?string $contractStart,
        ?string $contractEnd,
        string $periodStart,
        string $periodEnd,
        float $expectedActual
    ): void {
        $run = new PayrollRun([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
        $employee = new Employee([
            'contract_start' => $contractStart,
            'contract_end' => $contractEnd,
        ]);

        $worked = (new PayrollCalculator())->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $worked['working_days']);
        $this->assertSame($expectedActual, $worked['actual_days_worked']);
        $this->assertSame(0.0, $worked['overtime_hours']);
        // F-20 (#1816) : aucun log de présence → fallback prorata.
        $this->assertFalse($worked['has_attendance_data']);
    }
}
