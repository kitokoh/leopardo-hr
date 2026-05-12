<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\EstimationService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly EstimationService $estimationService) {}

    public function index(): View
    {
        $company = currentCompany();
        $today = now('UTC')->setTimezone($company->timezone)->toDateString();

        // 1. Statistiques globales (sur tous les employés actifs)
        $allEmployees = Employee::query()
            ->select(['id', 'salary_type', 'salary_base', 'hourly_rate'])
            ->where('status', 'active')
            ->get();

        $logsByEmployeeAll = AttendanceLog::query()
            ->select(['id', 'employee_id', 'status', 'check_in', 'check_out', 'hours_worked', 'overtime_hours'])
            ->where('date', $today)
            ->where('session_number', 1)
            ->get()
            ->keyBy('employee_id');

        $present = 0;
        $late = 0;
        $totalEstimated = 0.0;

        foreach ($allEmployees as $employee) {
            $log = $logsByEmployeeAll->get($employee->id);
            $attendanceStatus = $log?->status ?? 'absent';

            if ($attendanceStatus !== 'absent') {
                $present++;
            }
            if ($attendanceStatus === 'late') {
                $late++;
            }

            $summary = $this->estimationService->dailySummaryFromLog($employee, $log, $today);
            $totalEstimated += (float) $summary['total_estimated'];
        }

        // 2. Pagination pour la liste du tableau
        $perPage = max(1, min(100, (int) request()->integer('per_page', 20)));
        $paginator = Employee::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'salary_type', 'salary_base', 'hourly_rate'])
            ->where('status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($perPage);

        $rows = [];
        foreach ($paginator->items() as $employee) {
            $log = $logsByEmployeeAll->get($employee->id);
            $attendanceStatus = $log?->status ?? 'absent';
            $summary = $this->estimationService->dailySummaryFromLog($employee, $log, $today);

            $rows[] = [
                'employee' => $employee,
                'attendance_status' => $attendanceStatus,
                'check_in' => $log?->check_in?->setTimezone($company->timezone)->format('H:i'),
                'check_out' => $log?->check_out?->setTimezone($company->timezone)->format('H:i'),
                'hours' => $summary['hours_worked'] ?? 0.0,
                'due' => $summary['total_estimated'] ?? 0.0,
                'currency' => $summary['currency'] ?? $company->currency,
            ];
        }

        return view('dashboard', [
            'company' => $company,
            'today' => $today,
            'employeesTotal' => $allEmployees->count(),
            'presentCount' => $present,
            'lateCount' => $late,
            'totalEstimated' => round($totalEstimated, 2),
            'currency' => $company->currency,
            'rows' => $rows,
            'paginator' => $paginator,
        ]);
    }
}
