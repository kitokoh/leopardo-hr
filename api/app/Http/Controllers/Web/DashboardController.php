<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Infrastructure\Services\EstimationService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly EstimationService $estimationService) {}

    public function index(): View
    {
        $company = currentCompany();
        $today = now('UTC')->setTimezone($company->timezone)->toDateString();

        // 1. Statistiques globales (agrégats SQL — plus de chargement complet
        // en mémoire de tous les employés actifs ; issue #1471, audit T18).
        $employeesTotal = (int) Employee::query()
            ->where('status', 'active')
            ->count();

        $logsByEmployeeAll = AttendanceLog::query()
            ->select(['id', 'employee_id', 'date', 'session_number', 'status', 'check_in', 'check_out', 'hours_worked', 'overtime_hours', 'work_type', 'late_minutes'])
            ->where('date', $today)
            ->get()
            ->groupBy('employee_id');

        // Seuls les employés actifs ayant pointé aujourd'hui sont chargés :
        // l'estimation des absents est nulle par construction, et le nombre de
        // lignes est ainsi borné par l'activité du jour, pas par la taille du
        // tenant.
        $presentEmployees = Employee::query()
            ->select(['id', 'matricule', 'first_name', 'last_name', 'salary_type', 'salary_base', 'hourly_rate'])
            ->where('status', 'active')
            ->whereIn('id', $logsByEmployeeAll->keys())
            ->get()
            ->keyBy('id');

        $present = $presentEmployees->count();
        $late = $presentEmployees
            ->filter(fn (Employee $employee) => ($logsByEmployeeAll->get($employee->id) ?? collect())
                ->contains(fn (AttendanceLog $log) => $log->status === 'late'))
            ->count();
        $totalEstimated = 0.0;

        foreach ($presentEmployees as $employee) {
            $logs = $logsByEmployeeAll->get($employee->id, collect());
            $summary = $this->estimationService->dailySummaryFromLogs($employee, $logs, $today);
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
            $logs = $logsByEmployeeAll->get($employee->id, collect());
            $log = $logs
                ->sortByDesc(fn (AttendanceLog $log) => ($log->check_out === null ? 100000 : 0) + (int) $log->session_number)
                ->first();
            $attendanceStatus = $log?->status ?? 'absent';
            $summary = $this->estimationService->dailySummaryFromLogs($employee, $logs, $today);

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
            'employeesTotal' => $employeesTotal,
            'presentCount' => $present,
            'lateCount' => $late,
            'totalEstimated' => round($totalEstimated, 2),
            'currency' => $company->currency,
            'rows' => $rows,
            'paginator' => $paginator,
        ]);
    }
}

