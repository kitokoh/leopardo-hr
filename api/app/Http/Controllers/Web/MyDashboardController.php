<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Espace personnel de l'employe (applicable a tout role : employee, manager).
 *
 * Route : GET /me
 * Middleware : auth:web + tenant + employee
 */
class MyDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->user();
        $company = app()->bound('current_company') ? app('current_company') : $employee->company;

        $timezone = $company?->timezone ?? 'Africa/Algiers';
        $today = now('UTC')->setTimezone($timezone)->toDateString();
        $monthStart = now('UTC')->setTimezone($timezone)->startOfMonth()->toDateString();

        $todayLog = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('session_number', 1)
            ->first();

        $monthLogs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$monthStart, $today])
            ->orderByDesc('date')
            ->orderByDesc('session_number')
            ->get();

        $hoursMonth = (float) $monthLogs->sum(fn ($log) => (float) ($log->hours_worked ?? 0));
        $presentDays = $monthLogs->pluck('date')->unique()->count();

        return view('me.dashboard', [
            'employee' => $employee,
            'company' => $company,
            'today' => $today,
            'todayLog' => $todayLog,
            'monthLogs' => $monthLogs,
            'hoursMonth' => round($hoursMonth, 2),
            'presentDays' => $presentDays,
        ]);
    }
}
