<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class AttendanceMonthlyReportService
{
    public function build(Company $company, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01', $company->timezone)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::query()
            ->select(['id', 'company_id', 'first_name', 'last_name', 'matricule', 'status', 'salary_type', 'salary_base', 'hourly_rate'])
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get();

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
            ->get();

        $logsByEmployee = $logs->groupBy('employee_id');

        $rows = $employees
            ->map(fn (Employee $employee): array => $this->employeeRow($employee, $logsByEmployee->get($employee->id, collect())))
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
                    'month' => $month,
                    'date_from' => $start->toDateString(),
                    'date_to' => $end->toDateString(),
                ],
                'totals' => $totals,
                'employees' => $rows,
            ],
        ];
    }

    public function toCsv(array $report): Response
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            abort(500, 'Unable to build CSV export.');
        }

        fputcsv($handle, ['employee_id', 'matricule', 'name', 'worked_days', 'worked_hours', 'overtime_hours', 'late_minutes', 'missing_check_outs', 'manual_corrections', 'estimated_hourly_rate', 'estimated_gross_amount', 'estimated_overtime_amount']);

        foreach ($report['data']['employees'] as $row) {
            fputcsv($handle, [
                $row['employee_id'],
                $row['matricule'],
                $row['name'],
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

        return response($csv ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attendance-monthly-report-'.$report['data']['period']['month'].'.csv"',
        ]);
    }

    public function toPdf(array $report): Response
    {
        $html = view('pdf.attendance-monthly-report', [
            'report' => $report['data'],
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->download('attendance-monthly-report-'.$report['data']['period']['month'].'.pdf');
    }

    private function employeeRow(Employee $employee, Collection $logs): array
    {
        return [
            'employee_id' => $employee->id,
            'matricule' => $employee->matricule,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
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

    private function estimatedGrossAmount(Employee $employee, Collection $logs): float
    {
        $workedHours = (float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->hours_worked);

        if ($workedHours <= 0) {
            return 0.0;
        }

        return round($workedHours * $this->estimatedHourlyRate($employee), 2);
    }

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
