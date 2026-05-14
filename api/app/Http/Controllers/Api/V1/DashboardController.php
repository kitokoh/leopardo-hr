<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
}
