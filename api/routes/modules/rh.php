<?php

/**
 * Routes RH — modules 1-7 complets.
 * APV L.08 — Un module = un route group Laravel.
 */

use App\Http\Controllers\Api\V1\AbsenceController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\BiometricEnrollmentController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EstimationController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\KioskController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SalaryAdvanceController;
use App\Http\Controllers\Api\V1\ScheduleController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant'])->group(function (): void {

    // ── Employees ─────────────────────────────────────────────────────────────
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee');
    Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee');
    Route::post('/employees/{employee}/archive', [EmployeeController::class, 'archive'])->whereNumber('employee');

    // ── Estimations ───────────────────────────────────────────────────────────
    Route::get('/employees/{employee}/daily-summary', [EstimationController::class, 'dailySummary'])->whereNumber('employee');
    Route::get('/employees/{employee}/quick-estimate', [EstimationController::class, 'quickEstimate'])->whereNumber('employee');
    Route::get('/employees/{employee}/receipt', [EstimationController::class, 'receipt'])->whereNumber('employee');

    // ── Self-service ──────────────────────────────────────────────────────────
    Route::get('/me/daily-summary', [MeController::class, 'dailySummary']);
    Route::get('/me/quick-estimate', [MeController::class, 'quickEstimate']);
    Route::get('/me/monthly-summary', [MeController::class, 'monthlySummary']);

    // ── Attendance ────────────────────────────────────────────────────────────
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::put('/attendance/{attendanceLog}', [AttendanceController::class, 'update'])->whereNumber('attendanceLog');

    // ── Invitations ───────────────────────────────────────────────────────────
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend']);

    // ── Biometrics & Kiosks ───────────────────────────────────────────────────
    Route::get('/biometric-enrollment-requests', [BiometricEnrollmentController::class, 'index']);
    Route::post('/biometric-enrollment-requests/{id}/approve', [BiometricEnrollmentController::class, 'approve']);
    Route::post('/biometric-enrollment-requests/{id}/reject', [BiometricEnrollmentController::class, 'reject']);
    Route::post('/kiosks', [KioskController::class, 'register']);

    // ── Module 1 — Absences ───────────────────────────────────────────────────
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::post('/absences', [AbsenceController::class, 'store']);
    Route::get('/absences/{absence}', [AbsenceController::class, 'show'])->whereNumber('absence');
    Route::put('/absences/{absence}/approve', [AbsenceController::class, 'approve'])->whereNumber('absence');
    Route::put('/absences/{absence}/reject', [AbsenceController::class, 'reject'])->whereNumber('absence');
    Route::delete('/absences/{absence}', [AbsenceController::class, 'destroy'])->whereNumber('absence');

    // ── Module 2 — Salary Advances ────────────────────────────────────────────
    Route::get('/salary-advances', [SalaryAdvanceController::class, 'index']);
    Route::post('/salary-advances', [SalaryAdvanceController::class, 'store']);
    Route::get('/salary-advances/{salaryAdvance}', [SalaryAdvanceController::class, 'show'])->whereNumber('salaryAdvance');
    Route::put('/salary-advances/{salaryAdvance}/approve', [SalaryAdvanceController::class, 'approve'])->whereNumber('salaryAdvance');
    Route::put('/salary-advances/{salaryAdvance}/reject', [SalaryAdvanceController::class, 'reject'])->whereNumber('salaryAdvance');
    Route::delete('/salary-advances/{salaryAdvance}', [SalaryAdvanceController::class, 'destroy'])->whereNumber('salaryAdvance');

    // ── Module 3 — Payrolls ───────────────────────────────────────────────────
    Route::get('/payrolls', [PayrollController::class, 'index']);
    Route::post('/payrolls', [PayrollController::class, 'store']);
    Route::get('/payrolls/{payroll}', [PayrollController::class, 'show'])->whereNumber('payroll');
    Route::put('/payrolls/{payroll}', [PayrollController::class, 'update'])->whereNumber('payroll');
    Route::patch('/payrolls/{payroll}', [PayrollController::class, 'update'])->whereNumber('payroll');
    Route::put('/payrolls/{payroll}/validate', [PayrollController::class, 'validate'])->whereNumber('payroll');
    Route::delete('/payrolls/{payroll}', [PayrollController::class, 'destroy'])->whereNumber('payroll');

    // ── Module 4 — HR Referentials ────────────────────────────────────────────
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])->whereNumber('department');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->whereNumber('department');
    Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->whereNumber('department');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->whereNumber('department');

    Route::get('/positions', [PositionController::class, 'index']);
    Route::post('/positions', [PositionController::class, 'store']);
    Route::get('/positions/{position}', [PositionController::class, 'show'])->whereNumber('position');
    Route::put('/positions/{position}', [PositionController::class, 'update'])->whereNumber('position');
    Route::patch('/positions/{position}', [PositionController::class, 'update'])->whereNumber('position');
    Route::delete('/positions/{position}', [PositionController::class, 'destroy'])->whereNumber('position');

    Route::get('/sites', [SiteController::class, 'index']);
    Route::post('/sites', [SiteController::class, 'store']);
    Route::get('/sites/{site}', [SiteController::class, 'show'])->whereNumber('site');
    Route::put('/sites/{site}', [SiteController::class, 'update'])->whereNumber('site');
    Route::patch('/sites/{site}', [SiteController::class, 'update'])->whereNumber('site');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->whereNumber('site');

    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::post('/schedules', [ScheduleController::class, 'store']);
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show'])->whereNumber('schedule');
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->whereNumber('schedule');
    Route::patch('/schedules/{schedule}', [ScheduleController::class, 'update'])->whereNumber('schedule');
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->whereNumber('schedule');

    // ── Module 5 — Notifications ──────────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->whereNumber('notification');

    // ── Module 7 — Projects & Tasks ───────────────────────────────────────────
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->whereNumber('project');

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->whereNumber('task');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->whereNumber('task');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'addComment'])->whereNumber('task');
});

// ── Kiosk — auth par X-Kiosk-Token ────────────────────────────────────────────
Route::middleware(['throttle:api'])->group(function (): void {
    Route::get('/kiosks/{deviceCode}/roster', [KioskController::class, 'roster']);
    Route::post('/kiosks/{deviceCode}/punch', [KioskController::class, 'punch']);
    Route::post('/kiosks/{deviceCode}/sync', [KioskController::class, 'sync']);
});
