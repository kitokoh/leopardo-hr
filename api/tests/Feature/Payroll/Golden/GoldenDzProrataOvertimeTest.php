<?php

namespace Tests\Feature\Payroll\Golden;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
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
 * Pas de base de données pour les cas purs (modèles non persistés, casts
 * date/Carbon) ; les cas F-20 (#1816) sont DB-backed (RefreshTenantDatabase)
 * car ils exigent de vrais AttendanceLogs.
 */
class GoldenDzProrataOvertimeTest extends TestCase
{
    use RefreshTenantDatabase;

    public static function prorataProvider(): array
    {
        return [
            'mois complet' => [60000.0, 22.0, 22.0, 60000.0],
            'absence 1 jour (21/22)' => [60000.0, 22.0, 21.0, 57272.73],   // retenue 2 727,27
            'congés sans solde 5 j' => [60000.0, 22.0, 17.0, 46363.64],   // retenue 13 636,36
            'entrée 15/07 (12,06/22)' => [60000.0, 22.0, 12.06, 32890.91],
            // #5149 — sortie 10/07 : 22 × 10/31 = 7,10 j → 60 000 × 7,10/22
            // (DZ_COMPLIANCE.md §5, tableau « sortie 10/07 ») — retenue 40 636,36.
            'sortie 10/07 (7,10/22)' => [60000.0, 22.0, 7.1, 19363.64],
            'zéro jour' => [60000.0, 22.0, 0.0, 0.0],
        ];
    }

    #[DataProvider('prorataProvider')]
    public function test_golden_dz_prorated_base(float $base, float $working, float $actual, float $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator)->computeProratedBase($base, $working, $actual));
    }

    public static function overtimeProvider(): array
    {
        return [
            'zéro heure' => [60000.0, 0.0, 0.0],
            // Issue #2685 : précision complète jusqu'à l'arrondi final —
            // 60000/173,33 = 346,160503… (non arrondi avant les majorations).
            // Issue #5266 (écart E2) : palier unique ≥ 50 % (art. 32 loi
            // 90-11) — l'ancien barème conventionnel « 10 h @ 25 % puis
            // 50 % » est écarté (sous le minimum légal pour les 10 1res h).
            '5 h à 50 %' => [60000.0, 5.0, 2596.20],
            '10 h à 50 %' => [60000.0, 10.0, 5192.41],
            '15 h à 50 %' => [60000.0, 15.0, 7788.61],
            '11 h à 50 %' => [60000.0, 11.0, 5711.65],
        ];
    }

    #[DataProvider('overtimeProvider')]
    public function test_golden_dz_overtime_pay(float $base, float $hours, float $expected): void
    {
        $this->assertSame($expected, (new PayrollCalculator)->computeOvertimePay($base, $hours, 10, new AlgeriaPayrollRules));
    }

    public static function workedDaysProvider(): array
    {
        // [contrat_start, contrat_end, period_start, period_end, expected_actual]
        return [
            'plein mois (01→31/07)' => ['2026-07-01', null, '2026-07-01', '2026-07-31', 22.0],
            'entrée 15/07 (17 j sur 31)' => ['2026-07-15', null, '2026-07-01', '2026-07-31', 12.06],
            'sortie 10/07 (10 j sur 31)' => ['2026-07-01', '2026-07-10', '2026-07-01', '2026-07-31', 7.1],
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

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $worked['working_days']);
        $this->assertSame($expectedActual, $worked['actual_days_worked']);
        $this->assertSame(0.0, $worked['overtime_hours']);
        // Fallback (0 log) : pas de données de pointage.
        $this->assertFalse($worked['has_attendance_data']);
    }

    /**
     * F-20 (#1816) — 18 logs de pointage valides sur la période → le nombre
     * de jours travaillés vient des jours réellement pointés, plus du
     * prorata contrat : actual_days_worked = 18.0.
     */
    public function test_actual_days_from_18_attendance_logs(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §5, F-20) :
        //   18 jours distincts avec log valide sur juillet 2026 → 18,0
        //   (remplace le prorata calendaire 22,0 du fallback).
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        foreach (range(1, 18) as $day) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => "2026-07-{$day}",
                'status' => 'ontime',
            ]);
        }

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $worked['working_days']);
        $this->assertSame(18.0, $worked['actual_days_worked']);
        $this->assertTrue($worked['has_attendance_data']);
    }

    /**
     * F-20 (#1816) — aucun log de pointage → fallback prorata contrat :
     * mois complet (01→31/07) → actual_days_worked = 22.0.
     */
    public function test_actual_days_fallback_no_logs(): void
    {
        // Calcul manuel (DZ_COMPLIANCE.md §5) : 0 log valide → prorata
        // contrat inchangé — plein mois = 22,0 jours ouvrés.
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'contract_start' => '2025-01-01',
        ]);
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        $worked = (new PayrollCalculator)->computeWorkedDays($run, $employee);

        $this->assertSame(22.0, $worked['working_days']);
        $this->assertSame(22.0, $worked['actual_days_worked']);
        $this->assertFalse($worked['has_attendance_data']);
    }
}
