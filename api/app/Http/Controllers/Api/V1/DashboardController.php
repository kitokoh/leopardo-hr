<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;

        $employees = DB::table('employees')
            ->where('company_id', $companyId)
            ->selectRaw("count(*) as total, count(case when status = 'active' then 1 end) as active")
            ->first();

        $departments = DB::table('departments')
            ->where('company_id', $companyId)
            ->count();

        $todayAttendance = DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereDate('check_in', now()->toDateString())
            ->count();

        $pendingAbsences = DB::table('absences')
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'data' => [
                'employees_total' => $employees->total ?? 0,
                'employees_active' => $employees->active ?? 0,
                'departments' => $departments,
                'today_attendance' => $todayAttendance,
                'pending_absences' => $pendingAbsences,
            ],
        ]);
    }

    public function managerDigest(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = (string) $user->company_id;
        $today = now()->toDateString();
        $employeeIds = $this->scopedEmployeeIds($user);

        $teamSize = count($employeeIds);
        $present = $this->countTodayPresent($companyId, $today, $employeeIds);
        $late = $this->countTodayLate($companyId, $today, $employeeIds);
        $openSessions = $this->countOpenSessions($companyId, $today, $employeeIds);
        $pendingAbsences = $this->countPendingByTable('absences', $companyId, $employeeIds);
        $pendingAdvances = $this->countPendingByTable('salary_advances', $companyId, $employeeIds);
        $pendingCorrections = $this->countPendingByTable('attendance_correction_requests', $companyId, $employeeIds);
        $pendingActions = $pendingAbsences + $pendingAdvances + $pendingCorrections;

        return response()->json([
            'data' => [
                'date' => $today,
                'team_scope' => $this->managerTeamScope($user),
                'team_size' => $teamSize,
                'present' => $present,
                'late' => $late,
                'open_sessions' => $openSessions,
                'pending_actions' => $pendingActions,
                'pending_absences' => $pendingAbsences,
                'pending_salary_advances' => $pendingAdvances,
                'pending_corrections' => $pendingCorrections,
                'items' => [
                    [
                        'kind' => 'attendance',
                        'label' => 'Présences enregistrées',
                        'count' => $present,
                        'severity' => 'success',
                        'route' => '/manager/attendance',
                    ],
                    [
                        'kind' => 'late',
                        'label' => 'Retards à surveiller',
                        'count' => $late,
                        'severity' => $late > 0 ? 'warning' : 'success',
                        'route' => '/manager/anomalies',
                    ],
                    [
                        'kind' => 'actions',
                        'label' => 'Actions RH en attente',
                        'count' => $pendingActions,
                        'severity' => $pendingActions > 0 ? 'info' : 'success',
                        'route' => '/approvals',
                    ],
                ],
            ],
        ]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;

        $activities = DB::table('audit_logs')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit($request->integer('limit', 20))
            ->get(['id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'created_at']);

        return response()->json(['data' => $activities]);
    }

    public function kpi(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;
        $month = $request->input('month', now()->format('Y-m'));
        $periodStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $turnover = DB::table('employees')
            ->where('company_id', $companyId)
            ->whereNotNull('archived_at')
            ->whereBetween('archived_at', [$periodStart, $periodEnd])
            ->count();

        $hires = DB::table('employees')
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();

        $absenceRate = 0.0;
        $totalEmployees = DB::table('employees')->where('company_id', $companyId)->where('status', 'active')->count();
        if ($totalEmployees > 0) {
            $absences = DB::table('absences')
                ->where('company_id', $companyId)
                ->where('status', 'approved')
                ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->count();
            $absenceRate = round(($absences / $totalEmployees) * 100, 1);
        }

        return response()->json([
            'data' => [
                'month' => $month,
                'turnover' => $turnover,
                'new_hires' => $hires,
                'absence_rate' => $absenceRate,
                'total_active_employees' => $totalEmployees,
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function scopedEmployeeIds(Employee $manager): array
    {
        $query = DB::table('employees')
            ->where('company_id', $manager->company_id)
            ->where('status', 'active');

        if (! in_array($manager->manager_role, ['principal', 'rh'], true)) {
            $query->where(function ($scope) use ($manager): void {
                $scope->where('manager_id', $manager->id)
                    ->orWhere('id', $manager->id);
            });
        }

        return $query->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    private function managerTeamScope(Employee $manager): string
    {
        return in_array($manager->manager_role, ['principal', 'rh'], true)
            ? 'company'
            : 'managed_team';
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countTodayPresent(string $companyId, string $today, array $employeeIds): int
    {
        if ($employeeIds === [] || ! Schema::hasTable('attendance_logs')) {
            return 0;
        }

        return DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->distinct('employee_id')
            ->count('employee_id');
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countTodayLate(string $companyId, string $today, array $employeeIds): int
    {
        if ($employeeIds === [] || ! Schema::hasTable('attendance_logs')) {
            return 0;
        }

        return DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('date', $today)
            ->where(function ($query): void {
                $query->where('late_minutes', '>', 0)
                    ->orWhere('status', 'late');
            })
            ->distinct('employee_id')
            ->count('employee_id');
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countOpenSessions(string $companyId, string $today, array $employeeIds): int
    {
        if ($employeeIds === [] || ! Schema::hasTable('attendance_logs')) {
            return 0;
        }

        return DB::table('attendance_logs')
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->count();
    }

    /**
     * @param  list<int>  $employeeIds
     */
    private function countPendingByTable(string $table, string $companyId, array $employeeIds): int
    {
        if ($employeeIds === [] || ! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)
            ->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'pending')
            ->count();
    }
}
