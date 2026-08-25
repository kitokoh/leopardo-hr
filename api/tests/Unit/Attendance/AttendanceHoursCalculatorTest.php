<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Modules\Attendance\Infrastructure\Services\AttendanceHoursCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Règles de calcul unifiées du pointage (issue #5265).
 *
 * Jeu doré calculé à la main (DoD « les heures calculées correspondent au
 * contrôle manuel sur un jeu de données réel ») :
 * - 08:05 → 17:35, planning 08:00–17:00, pause 60 min, tolérance 0, seuil 8 h
 *   → retard 5 min ; brut 9 h 30 − 60 min de pause = 8,5 h ; HS 0,5 h.
 *
 * Les formules sont identiques aux blocs historiques d'AttendanceService
 * (parité prouvée par les suites Feature existantes) — le mode géo converge
 * désormais vers les mêmes règles (pauses déduites).
 */
class AttendanceHoursCalculatorTest extends TestCase
{
    private AttendanceHoursCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new AttendanceHoursCalculator();
    }

    public function test_golden_manual_calculation_late_5_hours_8_5_overtime_0_5(): void
    {
        $checkIn = Carbon::parse('2026-04-06 08:05:00', 'UTC');
        $checkOut = Carbon::parse('2026-04-06 17:35:00', 'UTC');
        $start = Carbon::parse('2026-04-06 08:00:00', 'UTC');

        // Contrôle manuel : 08:05 − 08:00 = +5 min (tolérance 0) → 5 min de retard.
        $late = $this->calculator->lateAssessment($checkIn, $start, 0);
        $this->assertSame(5, $late->late_minutes);
        $this->assertSame('late', $late->status);

        // Contrôle manuel : 08:05 → 17:35 = 9 h 30 brutes ; − 60 min de pause = 8,5 h ;
        // seuil 8 h → 0,5 h supplémentaires.
        $worked = $this->calculator->workedHours($checkIn, $checkOut, 'normal', 60, 8.0);
        $this->assertSame(8.5, $worked->hours_worked);
        $this->assertSame(0.5, $worked->overtime_hours);
    }

    public function test_exact_schedule_with_break_yields_no_overtime(): void
    {
        // 08:00 → 17:00 = 9 h brutes ; − 60 min = 8 h ; seuil 8 h → HS 0.
        $worked = $this->calculator->workedHours(
            Carbon::parse('2026-04-06 08:00:00', 'UTC'),
            Carbon::parse('2026-04-06 17:00:00', 'UTC'),
            'normal',
            60,
            8.0,
        );

        $this->assertSame(8.0, $worked->hours_worked);
        $this->assertSame(0.0, $worked->overtime_hours);
    }

    public function test_tolerance_absorbs_small_lateness(): void
    {
        // 08:05 avec tolérance 15 min → retard 0, statut ontime (formule historique).
        $late = $this->calculator->lateAssessment(
            Carbon::parse('2026-04-06 08:05:00', 'UTC'),
            Carbon::parse('2026-04-06 08:00:00', 'UTC'),
            15,
        );

        $this->assertSame(0, $late->late_minutes);
        $this->assertSame('ontime', $late->status);
    }

    public function test_early_check_in_is_never_late(): void
    {
        $late = $this->calculator->lateAssessment(
            Carbon::parse('2026-04-06 07:50:00', 'UTC'),
            Carbon::parse('2026-04-06 08:00:00', 'UTC'),
            0,
        );

        $this->assertSame(0, $late->late_minutes);
        $this->assertSame('ontime', $late->status);
    }

    public function test_overtime_work_type_counts_full_hours(): void
    {
        // work_type=overtime → les heures pleines comptent en HS (règle historique).
        $worked = $this->calculator->workedHours(
            Carbon::parse('2026-04-06 08:00:00', 'UTC'),
            Carbon::parse('2026-04-06 18:30:00', 'UTC'),
            'overtime',
            60,
            8.0,
        );

        $this->assertSame(9.5, $worked->hours_worked);
        $this->assertSame(9.5, $worked->overtime_hours);
    }

    public function test_non_work_types_yield_zero_hours(): void
    {
        // #2686 : break/resume ne comptent ni en heures ni en HS.
        foreach (['break', 'resume'] as $workType) {
            $worked = $this->calculator->workedHours(
                Carbon::parse('2026-04-06 08:00:00', 'UTC'),
                Carbon::parse('2026-04-06 10:00:00', 'UTC'),
                $workType,
                0,
                8.0,
            );

            $this->assertSame(0.0, $worked->hours_worked, $workType);
            $this->assertSame(0.0, $worked->overtime_hours, $workType);
        }
    }

    public function test_multi_session_days_do_not_deduct_break(): void
    {
        // Seule la 1re session de la journée déduit la pause (règle historique).
        $break = $this->calculator->effectiveBreakMinutes(2, 'normal', 60, null);
        $this->assertSame(0, $break);

        $break = $this->calculator->effectiveBreakMinutes(1, 'normal', 60, 'break');
        $this->assertSame(0, $break);

        $break = $this->calculator->effectiveBreakMinutes(1, 'normal', 60, null);
        $this->assertSame(60, $break);

        $break = $this->calculator->effectiveBreakMinutes(1, 'overtime', 60, null);
        $this->assertSame(0, $break);

        $break = $this->calculator->effectiveBreakMinutes(1, 'normal', null, null);
        $this->assertSame(0, $break);
    }

    public function test_no_schedule_break_means_zero_deduction(): void
    {
        $worked = $this->calculator->workedHours(
            Carbon::parse('2026-04-06 08:00:00', 'UTC'),
            Carbon::parse('2026-04-06 17:00:00', 'UTC'),
            'normal',
            0,
            8.0,
        );

        $this->assertSame(9.0, $worked->hours_worked);
        $this->assertSame(1.0, $worked->overtime_hours);
    }
}
