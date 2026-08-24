<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\HR\Domain\Models\Department;
use App\Support\CsvCellSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapports de pointage par période (issue #5268).
 *
 * Étend l'ancien rapport mensuel avec :
 *  - period=day|week|month (défaut month, rétro-compatible avec l'existant) ;
 *  - ancres date (Y-m-d) / week (Y-m-d — semaine ISO, lundi → dimanche) / month (Y-m) ;
 *  - filtres department_id (équipe) et employee_id (fiche individuelle) ;
 *  - exports CSV + PDF pour toutes les périodes ;
 *  - synthèse paie conservée (heures, HS, estimation brute).
 *
 * Le scope manager (PA2-SEC-002/003) est toujours appliqué via
 * Employee::visibleToManager() — les filtres ne peuvent qu'affiner la
 * visibilité, jamais l'élargir.
 */
class AttendanceReportService
{
    public const PERIOD_DAY = 'day';

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    public const PERIODS = [self::PERIOD_DAY, self::PERIOD_WEEK, self::PERIOD_MONTH];

    /**
     * @param  array{date?: string, week?: string, month?: string, department_id?: int|null, employee_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function build(
        Company $company,
        string $period = self::PERIOD_MONTH,
        array $filters = [],
        ?Employee $scopeActor = null,
    ): array {
        [$start, $end] = $this->periodRange($company, $period, $filters);

        // manager_role=dept is scoped to their own department only (PA2-SEC-002);
        // manager_role=superviseur is scoped to their own assigned team (PA2-SEC-003).
        $isScoped = $scopeActor !== null && $scopeActor->isTeamScoped();

        $query = Employee::query()
            ->select(['id', 'company_id', 'department_id', 'first_name', 'last_name', 'matricule', 'status', 'salary_type', 'salary_base', 'hourly_rate'])
            ->where('company_id', $company->id)
            ->when(isset($filters['department_id']), fn ($query) => $query->where('department_id', (int) $filters['department_id']))
            ->when(isset($filters['employee_id']), fn ($query) => $query->where('id', (int) $filters['employee_id']))
            ->orderBy('id');

        // Scope manager appliqué hors chaîne `when()` pour permettre le
        // narrowing PHPStan (PA2-SEC-002/003) : filtres et scope sont combinés.
        if ($scopeActor !== null && $scopeActor->isTeamScoped()) {
            $query->visibleToManager($scopeActor);
        }

        $employees = $query->get();

        $logs = AttendanceLog::query()
            ->select([
                'id',
                'company_id',
                'employee_id',
                'date',
                'check_in',
                'check_out',
                'method',
                'hours_worked',
                'overtime_hours',
                'late_minutes',
                'corrected_by',
            ])
            ->where('company_id', $company->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            // Toujours borné aux employés visibles (filtres + scope manager) :
            // un department_id / employee_id hors portée produit zéro ligne, jamais une fuite.
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get();

        $departmentNames = Department::query()
            ->where('company_id', $company->id)
            ->pluck('name', 'id');

        $logsByEmployee = $logs->groupBy('employee_id');

        $rows = $employees
            ->map(fn (Employee $employee): array => $this->employeeRow($employee, $logsByEmployee->get($employee->id, collect()), $departmentNames))
            ->values();

        $totals = [
            'employees' => $employees->count(),
            'attendance_logs' => $logs->count(),
            'worked_hours' => round((float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->hours_worked), 2),
            'overtime_hours' => round((float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->overtime_hours), 2),
            'late_minutes' => (int) $logs->sum(fn (AttendanceLog $log): int => (int) $log->late_minutes),
            'missing_check_outs' => $logs->filter(fn (AttendanceLog $log): bool => $log->check_in !== null && $log->check_out === null)->count(),
            'manual_corrections' => $logs->filter(fn (AttendanceLog $log): bool => $log->method === 'manual' || $log->corrected_by !== null)->count(),
            'worked_days' => $logs->filter(fn (AttendanceLog $log): bool => $log->check_in !== null)->pluck('date')->map(fn ($date): string => $date->format('Y-m-d'))->unique()->count(),
            'estimated_gross_payroll' => round((float) $rows->sum(fn (array $row): float => (float) $row['estimated_gross_amount']), 2),
            'estimated_overtime_pay' => round((float) $rows->sum(fn (array $row): float => (float) $row['estimated_overtime_amount']), 2),
        ];

        return [
            'data' => [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'currency' => $company->currency,
                    'timezone' => $company->timezone,
                ],
                'period' => [
                    'type' => $period,
                    // Rétro-compatibilité : `month` était le seul identifiant de
                    // période exposé — conservé pour les consommateurs existants.
                    'month' => $start->format('Y-m'),
                    'date_from' => $start->toDateString(),
                    'date_to' => $end->toDateString(),
                ],
                'totals' => $totals,
                'employees' => $rows,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function toCsv(array $report): Response
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            abort(500, 'CSV_EXPORT_FAILED');
        }

        fputcsv($handle, ['employee_id', 'matricule', 'name', 'department_id', 'department_name', 'worked_days', 'worked_hours', 'overtime_hours', 'late_minutes', 'missing_check_outs', 'manual_corrections', 'estimated_hourly_rate', 'estimated_gross_amount', 'estimated_overtime_amount']);

        foreach ($report['data']['employees'] as $row) {
            fputcsv($handle, [
                $row['employee_id'],
                // #4169 : matricule et nom sont des champs texte contrôlés par
                // l'utilisateur → neutralisation des préfixes de formule CSV.
                CsvCellSanitizer::neutralize((string) $row['matricule']),
                CsvCellSanitizer::neutralize((string) $row['name']),
                $row['department_id'],
                $row['department_name'] !== null ? CsvCellSanitizer::neutralize((string) $row['department_name']) : '',
                $row['worked_days'],
                $row['worked_hours'],
                $row['overtime_hours'],
                $row['late_minutes'],
                $row['missing_check_outs'],
                $row['manual_corrections'],
                $row['estimated_hourly_rate'],
                $row['estimated_gross_amount'],
                $row['estimated_overtime_amount'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $period = $report['data']['period'];

        return response($csv ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attendance-report-'.$period['type'].'-'.$period['date_from'].'_'.$period['date_to'].'.csv"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function toPdf(array $report): Response
    {
        $html = view('pdf.attendance-monthly-report', [
            'report' => $report['data'],
        ])->render();

        $period = $report['data']['period'];

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->download('attendance-report-'.$period['type'].'-'.$period['date_from'].'_'.$period['date_to'].'.pdf');
    }

    /**
     * @param  array{date?: string, week?: string, month?: string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(Company $company, string $period, array $filters): array
    {
        $timezone = $company->timezone;

        return match ($period) {
            self::PERIOD_DAY => $this->dayRange($timezone, $filters),
            self::PERIOD_WEEK => $this->weekRange($timezone, $filters),
            default => $this->monthRange($timezone, $filters),
        };
    }

    /**
     * @param  array{date?: string, week?: string, month?: string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dayRange(string $timezone, array $filters): array
    {
        $anchor = $filters['date'] ?? now($timezone)->toDateString();
        $day = Carbon::parse($anchor, $timezone);

        return [$day, $day->copy()];
    }

    /**
     * @param  array{date?: string, week?: string, month?: string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function weekRange(string $timezone, array $filters): array
    {
        $anchor = $filters['week'] ?? now($timezone)->toDateString();
        $date = Carbon::parse($anchor, $timezone);

        // Semaine ISO explicite (lundi → dimanche), indépendante de la locale PHP.
        return [$date->copy()->startOfWeek(Carbon::MONDAY), $date->copy()->endOfWeek(Carbon::SUNDAY)];
    }

    /**
     * @param  array{date?: string, week?: string, month?: string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthRange(string $timezone, array $filters): array
    {
        $month = $filters['month'] ?? now($timezone)->format('Y-m');
        $start = Carbon::parse($month.'-01', $timezone)->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * @param  Collection<int, AttendanceLog>  $logs
     * @param  Collection<int, string>  $departmentNames
     * @return array<string, int|string|float|null>
     */
    private function employeeRow(Employee $employee, Collection $logs, Collection $departmentNames): array
    {
        return [
            'employee_id' => $employee->id,
            'matricule' => $employee->matricule,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'department_id' => $employee->department_id,
            'department_name' => $employee->department_id !== null ? ($departmentNames->get($employee->department_id) ?? null) : null,
            'worked_days' => $logs->filter(fn (AttendanceLog $log): bool => $log->check_in !== null)
                ->pluck('date')
                ->map(fn ($date): string => $date->format('Y-m-d'))
                ->unique()
                ->count(),
            'worked_hours' => round((float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->hours_worked), 2),
            'overtime_hours' => round((float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->overtime_hours), 2),
            'late_minutes' => (int) $logs->sum(fn (AttendanceLog $log): int => (int) $log->late_minutes),
            'missing_check_outs' => $logs->filter(fn (AttendanceLog $log): bool => $log->check_in !== null && $log->check_out === null)->count(),
            'manual_corrections' => $logs->filter(fn (AttendanceLog $log): bool => $log->method === 'manual' || $log->corrected_by !== null)->count(),
            'estimated_hourly_rate' => $this->estimatedHourlyRate($employee),
            'estimated_gross_amount' => $this->estimatedGrossAmount($employee, $logs),
            'estimated_overtime_amount' => $this->estimatedOvertimeAmount($employee, $logs),
        ];
    }

    /**
     * @param  Collection<int, AttendanceLog>  $logs
     */
    private function estimatedGrossAmount(Employee $employee, Collection $logs): float
    {
        $workedHours = (float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->hours_worked);

        if ($workedHours <= 0) {
            return 0.0;
        }

        return round($workedHours * $this->estimatedHourlyRate($employee), 2);
    }

    /**
     * @param  Collection<int, AttendanceLog>  $logs
     */
    private function estimatedOvertimeAmount(Employee $employee, Collection $logs): float
    {
        $overtimeHours = (float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->overtime_hours);

        if ($overtimeHours <= 0) {
            return 0.0;
        }

        return round($overtimeHours * $this->estimatedHourlyRate($employee) * 1.5, 2);
    }

    private function estimatedHourlyRate(Employee $employee): float
    {
        if ((float) $employee->hourly_rate > 0) {
            return round((float) $employee->hourly_rate, 2);
        }

        if ((float) $employee->salary_base <= 0) {
            return 0.0;
        }

        return round((float) $employee->salary_base / 173.33, 2);
    }
}
