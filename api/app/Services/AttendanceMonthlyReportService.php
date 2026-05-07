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
            ->select(['id', 'company_id', 'first_name', 'last_name', 'matricule', 'status'])
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get();

        $logs = AttendanceLog::query()
            ->with(['employee:id,company_id,first_name,last_name,matricule'])
            ->where('company_id', $company->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $rows = $employees
            ->map(fn (Employee $employee): array => $this->employeeRow($employee, $logs->where('employee_id', $employee->id)))
            ->values();

        $totals = [
            'employees' => $employees->count(),
            'attendance_logs' => $logs->count(),
            'worked_hours' => round((float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->hours_worked), 2),
            'overtime_hours' => round((float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->overtime_hours), 2),
            'late_minutes' => (int) $logs->sum(fn (AttendanceLog $log): int => (int) $log->late_minutes),
            'missing_check_outs' => $logs->filter(fn (AttendanceLog $log): bool => $log->check_in !== null && $log->check_out === null)->count(),
            'manual_corrections' => $logs->filter(fn (AttendanceLog $log): bool => $log->method === 'manual' || $log->corrected_by !== null)->count(),
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

        fputcsv($handle, ['employee_id', 'matricule', 'name', 'worked_hours', 'overtime_hours', 'late_minutes', 'missing_check_outs', 'manual_corrections']);

        foreach ($report['data']['employees'] as $row) {
            fputcsv($handle, [
                $row['employee_id'],
                $row['matricule'],
                $row['name'],
                $row['worked_hours'],
                $row['overtime_hours'],
                $row['late_minutes'],
                $row['missing_check_outs'],
                $row['manual_corrections'],
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
            'worked_hours' => round((float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->hours_worked), 2),
            'overtime_hours' => round((float) $logs->sum(fn (AttendanceLog $log): float => (float) $log->overtime_hours), 2),
            'late_minutes' => (int) $logs->sum(fn (AttendanceLog $log): int => (int) $log->late_minutes),
            'missing_check_outs' => $logs->filter(fn (AttendanceLog $log): bool => $log->check_in !== null && $log->check_out === null)->count(),
            'manual_corrections' => $logs->filter(fn (AttendanceLog $log): bool => $log->method === 'manual' || $log->corrected_by !== null)->count(),
        ];
    }
}
