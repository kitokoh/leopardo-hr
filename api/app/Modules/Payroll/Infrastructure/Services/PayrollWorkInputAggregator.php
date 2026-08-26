<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Planning\Domain\Models\Absence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * #5591 (audit architecture 2026-08-26) — agrégats des entrées de travail
 * (heures sup, jours de présence, congés approuvés) extraits de
 * PayrollCalculator (god-object 1 676 lignes) : ce service isole les
 * REQUÊTES/AGrÉGATS du calcul pur.
 *
 * Regroupe :
 *  - `collectWorkInputs()`    : agrégats par-employé (chemin singleton) ;
 *  - `aggregateWorkInputs()`  : agrégats BATCH pour tout le run (#2687,
 *    ~3 requêtes au lieu de ~5/employé, repli par-employé sur échec) ;
 *  - `sumApprovedLeaveDays()` : clipping période des absences approuvées
 *    (#2672), partagé par les deux chemins.
 */
final class PayrollWorkInputAggregator
{
    public function collectWorkInputs(
        PayrollRun $run,
        Employee $employee,
        ?array $attendanceAgg = null,
        ?array $leaveAgg = null
    ): array {
        if ($attendanceAgg !== null && $leaveAgg !== null) {
            // Issue #2687 : agrégats batch (executeCalculateRun) — mêmes
            // valeurs que les requêtes par-employé ci-dessous.
            return [
                'overtime_hours' => (float) ($attendanceAgg['overtime_hours'] ?? 0.0),
                'paid_leave_days' => (float) ($leaveAgg['paid_leave_days'] ?? 0.0),
                'unpaid_leave_days' => (float) ($leaveAgg['unpaid_leave_days'] ?? 0.0),
            ];
        }

        $overtimeHours = AttendanceLog::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$run->period_start, $run->period_end])
            ->where('overtime_hours', '>', 0)
            ->whereNotIn('status', ['cancelled', 'rejected', 'incomplete'])
            ->sum('overtime_hours');

        $paidLeave = $this->sumApprovedLeaveDays($run, $employee, true);
        $unpaidLeave = $this->sumApprovedLeaveDays($run, $employee, false);

        return [
            'overtime_hours' => (float) $overtimeHours,
            'paid_leave_days' => $paidLeave,
            'unpaid_leave_days' => $unpaidLeave,
        ];
    }

    /**
     * Issue #2687 (T026) — agrégats groupés pour TOUS les employés du run :
     * jours de présence distincts + heures sup (attendance_logs) et congés
     * approuvés payés/non payés (absences, clipping période identique à
     * sumApprovedLeaveDays). ~3 requêtes au total au lieu de ~5 par employé.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array{0: array<int, array{distinct_days?: int, overtime_hours?: float}>, 1: array<int, array{paid_leave_days?: float, unpaid_leave_days?: float}>}
     */
    public function aggregateWorkInputs(PayrollRun $run, Collection $employees): array
    {
        $attendance = [];
        $leave = [];

        if (schemaTableExists('attendance_logs')) {
            try {
                $distinct = AttendanceLog::query()
                    ->selectRaw('employee_id, COUNT(DISTINCT date) AS distinct_days')
                    ->where('company_id', $run->company_id)
                    ->whereBetween('date', [$run->period_start, $run->period_end])
                    ->whereNotIn('status', ['absent', 'leave', 'holiday', 'incomplete'])
                    ->groupBy('employee_id')
                    ->get();

                foreach ($distinct as $row) {
                    $attendance[(int) $row->employee_id]['distinct_days'] = (int) $row->distinct_days;
                }

                $overtime = AttendanceLog::query()
                    ->selectRaw('employee_id, COALESCE(SUM(overtime_hours), 0) AS overtime_hours')
                    ->where('company_id', $run->company_id)
                    ->whereBetween('date', [$run->period_start, $run->period_end])
                    ->where('overtime_hours', '>', 0)
                    ->whereNotIn('status', ['cancelled', 'rejected', 'incomplete'])
                    ->groupBy('employee_id')
                    ->get();

                foreach ($overtime as $row) {
                    $attendance[(int) $row->employee_id]['overtime_hours'] = (float) $row->overtime_hours;
                }
            } catch (QueryException $e) {
                // Repli par-employé (les méthodes gardent leur try/catch).
                Log::warning('aggregateWorkInputs: repli par-employé — attendance_logs en échec', [
                    'company_id' => $run->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $absences = Absence::query()
                ->where('company_id', $run->company_id)
                ->where('status', 'approved')
                ->where('start_date', '<=', $run->period_end)
                ->where('end_date', '>=', $run->period_start)
                ->with('absenceType:id,is_paid')
                ->get(['id', 'employee_id', 'absence_type_id', 'start_date', 'end_date', 'days_count']);

            $periodStart = $run->period_start->copy()->startOfDay();
            $periodEnd = $run->period_end->copy()->startOfDay();

            foreach ($absences as $absence) {
                if ($absence->end_date === null || $absence->absenceType === null) {
                    continue;
                }

                $overlapStart = $absence->start_date->copy()->max($periodStart);
                $overlapEnd = $absence->end_date->copy()->min($periodEnd);

                if ($overlapEnd->lt($overlapStart)) {
                    continue;
                }

                $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
                $totalSpanDays = $absence->start_date->diffInDays($absence->end_date) + 1;

                $days = $totalSpanDays > 0
                    ? (float) $absence->days_count * ($overlapDays / $totalSpanDays)
                    : (float) $absence->days_count;

                $key = (int) $absence->employee_id;
                if ((bool) $absence->absenceType->is_paid) {
                    $leave[$key]['paid_leave_days'] = ($leave[$key]['paid_leave_days'] ?? 0.0) + $days;
                } else {
                    $leave[$key]['unpaid_leave_days'] = ($leave[$key]['unpaid_leave_days'] ?? 0.0) + $days;
                }
            }
        } catch (QueryException $e) {
            Log::warning('aggregateWorkInputs: repli par-employé — absences en échec', [
                'company_id' => $run->company_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Préseed zéro pour TOUS les employés actifs : quand le batch a réussi,
        // le chemin par-employé n'est plus sollicité (seul un échec du batch
        // déclenche le repli, et alors les tableaux restent vides → null).
        foreach ($employees as $employee) {
            $attendance[$employee->id] ??= ['distinct_days' => 0, 'overtime_hours' => 0.0];
            $leave[$employee->id] ??= ['paid_leave_days' => 0.0, 'unpaid_leave_days' => 0.0];
        }

        return [$attendance, $leave];
    }

    private function sumApprovedLeaveDays(PayrollRun $run, Employee $employee, bool $paid): float
    {
        $absences = Absence::query()
            ->where('company_id', $run->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $run->period_end)
            ->where('end_date', '>=', $run->period_start)
            ->whereHas('absenceType', function (Builder $q) use ($paid): void {
                $q->where('is_paid', $paid);
            })
            ->get(['start_date', 'end_date', 'days_count']);

        $periodStart = $run->period_start->copy()->startOfDay();
        $periodEnd = $run->period_end->copy()->startOfDay();
        $total = 0.0;

        foreach ($absences as $absence) {
            // Issue #2672 (QA 2026-08-15) — clipping sur la période : une
            // absence chevauchante (ex. 25 janv. → 5 févr.) était comptée en
            // TOTALITÉ dans les runs de janvier ET février (double déduction
            // dans le prorata). On ne compte que l'intersection avec la
            // période, au prorata du days_count stocké.
            if ($absence->end_date === null) {
                continue;
            }

            $overlapStart = $absence->start_date->copy()->max($periodStart);
            $overlapEnd = $absence->end_date->copy()->min($periodEnd);

            if ($overlapEnd->lt($overlapStart)) {
                continue;
            }

            $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
            $totalSpanDays = $absence->start_date->diffInDays($absence->end_date) + 1;

            $total += $totalSpanDays > 0
                ? (float) $absence->days_count * ($overlapDays / $totalSpanDays)
                : (float) $absence->days_count;
        }

        return $total;
    }
}
