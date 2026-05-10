<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\AttendanceLog;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HrReportController extends Controller
{
    public function headcount(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $total = Employee::where('status', 'active')->count();
        $byDepartment = Employee::where('status', 'active')
            ->select('employees.*')
            ->join('contracts', function ($join) {
                $join->on('contracts.employee_id', '=', 'employees.id')
                    ->where('contracts.status', '=', 'active');
            })
            ->select(DB::raw('contracts.department_id, count(*) as count'))
            ->groupBy('contracts.department_id')
            ->get();

        $byContractType = Contract::active()
            ->select(DB::raw('contract_type, count(*) as count'))
            ->groupBy('contract_type')
            ->get();

        $byGender = Employee::where('status', 'active')
            ->select(DB::raw('gender, count(*) as count'))
            ->groupBy('gender')
            ->get();

        return response()->json([
            'data' => [
                'total' => $total,
                'by_department' => $byDepartment,
                'by_contract_type' => $byContractType,
                'by_gender' => $byGender,
            ],
        ]);
    }

    public function turnover(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $months = $request->integer('months', 12);
        $since = Carbon::now()->subMonths($months)->startOfMonth();

        $hired = Employee::where('contract_start', '>=', $since)
            ->select(DB::raw("to_char(contract_start, 'YYYY-MM') as month, count(*) as count"))
            ->groupBy(DB::raw("to_char(contract_start, 'YYYY-MM')"))
            ->orderBy('month')
            ->get();

        $terminated = Contract::where('status', 'terminated')
            ->where('terminated_at', '>=', $since)
            ->select(DB::raw("to_char(terminated_at, 'YYYY-MM') as month, count(*) as count"))
            ->groupBy(DB::raw("to_char(terminated_at, 'YYYY-MM')"))
            ->orderBy('month')
            ->get();

        return response()->json([
            'data' => [
                'period_months' => $months,
                'hired' => $hired,
                'terminated' => $terminated,
            ],
        ]);
    }

    public function absenteeism(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $month = $request->integer('month', (int) now()->format('m'));
        $year = $request->integer('year', (int) now()->format('Y'));
        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $absences = Absence::where('status', 'approved')
            ->where('start_date', '<=', $periodEnd->toDateString())
            ->where('end_date', '>=', $periodStart->toDateString())
            ->with(['employee:id,first_name,last_name', 'absenceType:id,name,code'])
            ->get();

        $totalDays = $absences->sum('days_count');
        $byType = $absences->groupBy('absence_type_id')->map(fn ($group) => [
            'type' => $group->first()->absenceType?->name,
            'count' => $group->count(),
            'total_days' => $group->sum('days_count'),
        ])->values();

        return response()->json([
            'data' => [
                'period' => $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                'total_days' => $totalDays,
                'total_requests' => $absences->count(),
                'by_type' => $byType,
            ],
        ]);
    }

    public function payrollSummary(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $month = $request->integer('month', (int) now()->format('m'));
        $year = $request->integer('year', (int) now()->format('Y'));

        $payrolls = Payroll::forPeriod($month, $year)->get();

        return response()->json([
            'data' => [
                'period' => $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                'count' => $payrolls->count(),
                'total_gross' => $payrolls->sum('gross_salary'),
                'total_net' => $payrolls->sum('net_salary'),
                'total_ir' => $payrolls->sum('ir_amount'),
                'draft' => $payrolls->where('status', 'draft')->count(),
                'validated' => $payrolls->where('status', 'validated')->count(),
            ],
        ]);
    }

    public function overtime(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $month = $request->integer('month', (int) now()->format('m'));
        $year = $request->integer('year', (int) now()->format('Y'));
        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $overtimeData = AttendanceLog::where('date', '>=', $periodStart->toDateString())
            ->where('date', '<=', $periodEnd->toDateString())
            ->where('overtime_hours', '>', 0)
            ->select([
                'employee_id',
                DB::raw('sum(overtime_hours) as total_overtime'),
                DB::raw('count(*) as days_with_overtime'),
            ])
            ->groupBy('employee_id')
            ->orderByDesc('total_overtime')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'period' => $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                'employees' => $overtimeData,
            ],
        ]);
    }

    private function authorizeManager(Request $request): void
    {
        if (! $request->user()->isManager()) {
            abort(403);
        }
    }
}
