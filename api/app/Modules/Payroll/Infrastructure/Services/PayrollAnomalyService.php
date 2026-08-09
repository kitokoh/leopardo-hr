<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Programme FOCUS — F-28 : détection d'anomalies de paie (rapport pré-clôture).
 *
 * Règles : l'IA/le rapport ne modifie JAMAIS la paie (WriteToolPolicy) ;
 * ce service est en lecture seule et retourne une liste d'anomalies avec
 * un niveau de sévérité, destinée à une action humaine avant clôture.
 *
 * Détecteurs :
 *  1. doublons         — plusieurs bulletins pour le même employé dans un run
 *  2. incohérence      — brut ≠ somme des lignes earning ; net ≠ brut − déductions
 *  3. variance brute   — écart > 30 % du brut vs le run précédent du même employé
 */
class PayrollAnomalyService
{
    /** Seuil de variance brute vs mois précédent (30 %). */
    public const GROSS_VARIANCE_THRESHOLD = 0.30;

    /** Seuil (h) d'heures sup pointées non intégrées à la paie (F-20). */
    public const ATTENDANCE_OVERTIME_TOLERANCE_HOURS = 2.0;

    /**
     * @return array<int, array{type: string, severity: string, employee_id?: int, message: string}>
     */
    public function detectForRun(PayrollRun $run): array
    {
        $anomalies = [];

        $anomalies = array_merge($anomalies, $this->detectDuplicateSlips($run));
        $anomalies = array_merge($anomalies, $this->detectIncoherentSlips($run));
        $anomalies = array_merge($anomalies, $this->detectGrossVariance($run));
        $anomalies = array_merge($anomalies, $this->detectAttendanceDiscrepancy($run));

        return $anomalies;
    }

    /**
     * @return array<int, array{type: string, severity: string, employee_id: int, message: string}>
     */
    public function detectDuplicateSlips(PayrollRun $run): array
    {
        // NB : PostgreSQL n'autorise pas de référencer l'alias de SELECT dans
        // HAVING — on répète l'expression COUNT(*) (issue #1586-adjacente,
        // CI rouge : "column slip_count does not exist").
        $duplicates = PaySlip::query()
            ->where('payroll_run_id', $run->id)
            ->select('employee_id')
            ->selectRaw('COUNT(*) as slip_count')
            ->groupBy('employee_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $duplicates->map(fn ($row): array => [
            'type'        => 'duplicate_slip',
            'severity'    => 'high',
            'employee_id' => (int) $row->employee_id,
            'message'     => "Employé #{$row->employee_id} : {$row->slip_count} bulletins dans le même run",
        ])->all();
    }

    /**
     * @return array<int, array{type: string, severity: string, employee_id: int, message: string}>
     */
    public function detectIncoherentSlips(PayrollRun $run): array
    {
        $anomalies = [];

        foreach ($run->paySlips as $slip) {
            $slip->loadMissing('lines');

            // Un bulletin sans lignes détaillées (saisie manuelle, import) ne
            // peut pas être qualifié d'incohérent sur la base de la somme des
            // lignes (vide) — le contrôle brut/net s'applique sinon à chaque
            // bulletin créé sans lignes (faux positifs, cf. tests F-28).
            if ($slip->lines->isEmpty()) {
                continue;
            }

            $earnings = $slip->lines->where('type', 'earning')->sum('amount');
            $deductions = $slip->lines->where('type', 'deduction')->sum('amount');

            $grossMismatch = abs($slip->gross_salary - $earnings) > 0.01;
            $netMismatch = abs($slip->net_salary - ($slip->gross_salary - $deductions)) > 0.01;

            if ($grossMismatch || $netMismatch) {
                $anomalies[] = [
                    'type'        => 'incoherent_slip',
                    'severity'    => $grossMismatch ? 'high' : 'medium',
                    'employee_id' => (int) $slip->employee_id,
                    'message'     => sprintf(
                        'Bulletin #%d : brut %.2f vs somme lignes %.2f (%s) ; net %.2f vs brut−déductions %.2f (%s)',
                        $slip->id,
                        $slip->gross_salary,
                        $earnings,
                        $grossMismatch ? 'INCOHÉRENT' : 'ok',
                        $slip->net_salary,
                        $slip->gross_salary - $deductions,
                        $netMismatch ? 'INCOHÉRENT' : 'ok'
                    ),
                ];
            }
        }

        return $anomalies;
    }

    /**
     * @return array<int, array{type: string, severity: string, employee_id: int, message: string}>
     */
    public function detectGrossVariance(PayrollRun $run): array
    {
        $anomalies = [];

        $previousRun = PayrollRun::query()
            ->where('company_id', $run->company_id)
            ->where('id', '<', $run->id)
            ->where('status', '!=', 'draft')
            ->orderByDesc('period_end')
            ->first();

        if ($previousRun === null) {
            return $anomalies;
        }

        $previousSlips = $previousRun->paySlips()->get()->keyBy('employee_id');

        foreach ($run->paySlips as $slip) {
            $previous = $previousSlips->get((int) $slip->employee_id);

            if ($previous === null || (float) $previous->gross_salary <= 0.0) {
                continue;
            }

            $variance = abs(((float) $slip->gross_salary - (float) $previous->gross_salary) / (float) $previous->gross_salary);

            if ($variance > self::GROSS_VARIANCE_THRESHOLD) {
                $anomalies[] = [
                    'type'        => 'gross_variance',
                    'severity'    => $variance > 0.5 ? 'high' : 'medium',
                    'employee_id' => (int) $slip->employee_id,
                    'message'     => sprintf(
                        'Employé #%d : brut %.2f vs %.2f le mois précédent (variation %.1f%% > 30%%)',
                        $slip->employee_id,
                        $slip->gross_salary,
                        $previous->gross_salary,
                        $variance * 100
                    ),
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Programme FOCUS — F-20 (#1550) : écarts pointage → paie signalés avant
     * clôture. Compare les heures supplémentaires réellement pointées
     * (AttendanceLog non annulés/rejetés sur la période du run) avec les
     * heures intégrées au bulletin (`PaySlip.overtime_hours`).
     *
     * Lecture seule — aucune écriture automatique (WriteToolPolicy).
     *
     * @return array<int, array{type: string, severity: string, employee_id: int, message: string}>
     */
    public function detectAttendanceDiscrepancy(PayrollRun $run): array
    {
        $anomalies = [];

        $attendanceHoursByEmployee = AttendanceLog::query()
            ->where('company_id', $run->company_id)
            ->whereBetween('date', [$run->period_start, $run->period_end])
            ->where('overtime_hours', '>', 0)
            ->whereNotIn('status', ['cancelled', 'rejected', 'incomplete'])
            ->select('employee_id')
            ->selectRaw('SUM(overtime_hours) as total_hours')
            ->groupBy('employee_id')
            ->pluck('total_hours', 'employee_id');

        if ($attendanceHoursByEmployee->isEmpty()) {
            return $anomalies;
        }

        foreach ($run->paySlips as $slip) {
            $pointed = (float) ($attendanceHoursByEmployee->get((int) $slip->employee_id) ?? 0.0);
            $paid = (float) $slip->overtime_hours;

            if ($pointed <= 0.0) {
                continue;
            }

            $missing = round($pointed - $paid, 2);

            if ($missing > self::ATTENDANCE_OVERTIME_TOLERANCE_HOURS) {
                $anomalies[] = [
                    'type'        => 'attendance_vs_payroll',
                    'severity'    => $missing > self::ATTENDANCE_OVERTIME_TOLERANCE_HOURS * 2 ? 'high' : 'medium',
                    'employee_id' => (int) $slip->employee_id,
                    'message'     => sprintf(
                        'Employé #%d : %.2f h sup pointées mais %.2f h intégrées à la paie (écart %.2f h) — vérifier avant clôture (F-20)',
                        $slip->employee_id,
                        $pointed,
                        $paid,
                        $missing
                    ),
                ];
            }
        }

        return $anomalies;
    }
}