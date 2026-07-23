<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * PA2-ATT-012: readable, non-opaque punctuality and task-completion
 * indicators per employee.
 *
 * "Non opaque" (per the ticket's acceptance criteria) means every
 * number that feeds the final score is returned alongside it, so a
 * manager or the employee themselves can see exactly why the score is
 * what it is (e.g. "3 late arrivals totalling 47 minutes", not just a
 * bare "82/100"). Nothing here is a hidden/black-box ranking.
 */
class AttendanceRegularityService
{
    /**
     * Punctuality weighs more than task completion because it's the
     * primary signal the acceptance criteria asks for ("indicateurs
     * ponctualite et completion taches"); task completion is a
     * secondary signal and is entirely optional (an employee with zero
     * assigned tasks is not penalised for it, see taskCompletion()).
     */
    private const PUNCTUALITY_WEIGHT = 0.7;

    private const TASK_COMPLETION_WEIGHT = 0.3;

    /**
     * @return array<string, mixed>
     */
    public function summarize(Employee $employee, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        $logs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            // whereDate() (not whereBetween on the raw column) so the
            // comparison is correct regardless of whether the `date`
            // column is persisted as a bare date or a datetime string with
            // a time component (varies by driver/environment).
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->get();

        $expectedDays = $this->expectedWorkingDays($employee, $from, $to);
        $workedDates = $logs
            ->filter(fn (AttendanceLog $log): bool => $log->check_in !== null)
            ->pluck('date')
            ->map(fn ($date): string => $date->format('Y-m-d'))
            ->unique();

        $lateLogs = $logs->filter(fn (AttendanceLog $log): bool => (int) $log->late_minutes > 0);
        $missingCheckOuts = $logs->filter(fn (AttendanceLog $log): bool => $log->check_in !== null && $log->check_out === null);
        $manualCorrections = $logs->filter(fn (AttendanceLog $log): bool => $log->method === 'manual' || $log->corrected_by !== null);

        $absentDays = max(0, $expectedDays->count() - $workedDates->count());

        $punctuality = $this->punctualityBreakdown(
            expectedDays: $expectedDays->count(),
            workedDays: $workedDates->count(),
            absentDays: $absentDays,
            lateLogs: $lateLogs,
            missingCheckOuts: $missingCheckOuts->count(),
        );

        $taskCompletion = $this->taskCompletion($employee, $from, $to);

        $score = $this->combinedScore($punctuality['score'], $taskCompletion['score']);

        return [
            'data' => [
                'employee_id' => $employee->id,
                'period' => [
                    'date_from' => $from->toDateString(),
                    'date_to' => $to->toDateString(),
                ],
                'score' => $score,
                'score_label' => $this->scoreLabel($score),
                'breakdown' => [
                    'punctuality' => $punctuality,
                    'task_completion' => $taskCompletion,
                ],
                'weights' => [
                    'punctuality' => self::PUNCTUALITY_WEIGHT,
                    'task_completion' => self::TASK_COMPLETION_WEIGHT,
                ],
                'supporting_data' => [
                    'manual_corrections' => $manualCorrections->count(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function punctualityBreakdown(
        int $expectedDays,
        int $workedDays,
        int $absentDays,
        Collection $lateLogs,
        int $missingCheckOuts,
    ): array {
        if ($expectedDays === 0) {
            // Nothing was expected of the employee in this period (e.g. brand
            // new hire, or the whole window falls on rest days): a neutral
            // 100 avoids unfairly punishing someone for a period where they
            // had literally nothing to show up for, while still being
            // transparent about why (expected_days = 0 is visible below).
            return [
                'score' => 100.0,
                'expected_days' => 0,
                'worked_days' => $workedDays,
                'absent_days' => 0,
                'late_arrivals_count' => 0,
                'late_minutes_total' => 0,
                'missing_check_outs' => $missingCheckOuts,
            ];
        }

        $attendanceRate = $workedDays / $expectedDays;
        $lateRatio = $lateLogs->count() / max(1, $workedDays);
        $lateMinutesTotal = (int) $lateLogs->sum(fn (AttendanceLog $log): int => (int) $log->late_minutes);
        $missingCheckOutRatio = $missingCheckOuts / max(1, $workedDays);

        // Attendance rate carries the most weight (did they show up at
        // all?), lateness and missing check-outs are penalties on top of
        // that, floored at 0 so one bad period can't go negative.
        $score = max(0.0, min(100.0,
            ($attendanceRate * 100)
            - ($lateRatio * 30)
            - ($missingCheckOutRatio * 20)
        ));

        return [
            'score' => round($score, 2),
            'expected_days' => $expectedDays,
            'worked_days' => $workedDays,
            'absent_days' => $absentDays,
            'late_arrivals_count' => $lateLogs->count(),
            'late_minutes_total' => $lateMinutesTotal,
            'missing_check_outs' => $missingCheckOuts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskCompletion(Employee $employee, Carbon $from, Carbon $to): array
    {
        $tasks = Task::query()
            ->whereJsonContains('assigned_to', $employee->id)
            ->whereBetween('due_date', [$from, $to])
            ->get();

        if ($tasks->isEmpty()) {
            // No tasks assigned in this period: this dimension is excluded
            // from the combined score entirely (see combinedScore()) rather
            // than silently counted as 0/100, so an employee is never
            // penalised for a manager simply not assigning them tasks.
            return [
                'score' => null,
                'assigned_tasks' => 0,
                'completed_tasks' => 0,
                'overdue_tasks' => 0,
            ];
        }

        $completed = $tasks->filter(fn (Task $task): bool => $task->status === 'done');
        $overdue = $tasks->filter(fn (Task $task): bool => $task->status !== 'done' && $task->due_date->isPast());

        $score = round(($completed->count() / $tasks->count()) * 100, 2);

        return [
            'score' => $score,
            'assigned_tasks' => $tasks->count(),
            'completed_tasks' => $completed->count(),
            'overdue_tasks' => $overdue->count(),
        ];
    }

    private function combinedScore(float $punctualityScore, ?float $taskCompletionScore): float
    {
        if ($taskCompletionScore === null) {
            return round($punctualityScore, 2);
        }

        return round(
            ($punctualityScore * self::PUNCTUALITY_WEIGHT) + ($taskCompletionScore * self::TASK_COMPLETION_WEIGHT),
            2
        );
    }

    private function scoreLabel(float $score): string
    {
        return match (true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 50 => 'needs_attention',
            default => 'critical',
        };
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function expectedWorkingDays(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        $schedule = $employee->schedule;
        $restDayNumbers = $this->restDayNumbers($schedule?->rest_days);

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $approvedAbsenceDates = Absence::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            // An absence overlaps the requested period if it starts before
            // the period ends AND ends after (or on) the period starts;
            // this also catches absences that fully span the period
            // (started before $from and ends after $to), unlike a plain
            // whereBetween on start_date/end_date alone.
            ->where('start_date', '<=', $toDate)
            ->where('end_date', '>=', $fromDate)
            ->get()
            ->flatMap(function (Absence $absence): array {
                $days = [];
                $cursor = $absence->start_date->copy();
                $end = $absence->end_date ?? $absence->start_date;
                while ($cursor->lessThanOrEqualTo($end)) {
                    $days[] = $cursor->toDateString();
                    $cursor->addDay();
                }

                return $days;
            })
            ->unique();

        $days = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lessThanOrEqualTo($to)) {
            $isRestDay = in_array($cursor->dayOfWeekIso, $restDayNumbers, true);
            $isApprovedAbsence = $approvedAbsenceDates->contains($cursor->toDateString());

            if (! $isRestDay && ! $isApprovedAbsence) {
                $days->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * `rest_days` on Schedule is stored as an array of ISO-8601 weekday
     * numbers (1 = Monday .. 7 = Sunday); default to a Saturday/Sunday
     * weekend when the employee has no schedule assigned yet, matching the
     * platform-wide default used elsewhere (PayrollCalculator/CountryRules
     * weeklyRestDays()).
     *
     * @param  array<mixed>|null  $restDays
     * @return array<int>
     */
    private function restDayNumbers(?array $restDays): array
    {
        if ($restDays === null || $restDays === []) {
            return [6, 7];
        }

        return array_map('intval', $restDays);
    }
}
