<?php

declare(strict_types=1);

/**
 * Routes RH — modules 1-7 complets.
 * APV L.08 — Un module = un route group Laravel.
 *
 * Namespaces migrés vers App\Modules\* (nouvelle architecture modulaire).
 */

// ── Modules migrés ─────────────────────────────────────────────────────────────
use App\Modules\Absence\Interfaces\Api\V1\Controllers\AbsenceController;
use App\Modules\Attendance\Interfaces\Api\V1\AttendanceController;
use App\Modules\Attendance\Interfaces\Api\V1\BiometricEnrollmentController;
use App\Modules\Attendance\Interfaces\Api\V1\KioskController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\DepartmentController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\EmployeeImportController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\EvaluationController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\InvitationController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\MeController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\OnboardingQrController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\PositionController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\SiteController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\AnnouncementController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\NotificationController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\NotificationStreamController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\SseTokenController;
use App\Modules\Payroll\Interfaces\Api\V1\EstimationController;
use App\Modules\Payroll\Interfaces\Api\V1\LedgerController;
use App\Modules\Payroll\Interfaces\Api\V1\PayrollController;
use App\Modules\Payroll\Interfaces\Api\V1\PayrollCycleController;
use App\Modules\Payroll\Interfaces\Api\V1\SalaryAdvanceController;
use App\Modules\Planning\Interfaces\Api\V1\ProjectController;
use App\Modules\Planning\Interfaces\Api\V1\ScheduleController;
use App\Modules\Planning\Interfaces\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])->group(function (): void {

    // ── Employees ─────────────────────────────────────────────────────────────
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee');
    Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee');
    Route::post('/employees/{employee}/archive', [EmployeeController::class, 'archive'])->whereNumber('employee');
    Route::post('/employees/import', [EmployeeImportController::class, 'import']);
    Route::get('/employees/import-template', [EmployeeImportController::class, 'template']);
    Route::get('/me/qr-profile', [OnboardingQrController::class, 'employeeProfile']);
    Route::get('/company/qr-onboarding', [OnboardingQrController::class, 'companyOnboarding']);
    Route::post('/company/qr-onboarding/scan-employee', [OnboardingQrController::class, 'scanEmployee']);
    Route::post('/company/qr-onboarding/create-employee', [OnboardingQrController::class, 'createEmployeeFromQr']);
    Route::post('/me/company-qr/scan', [OnboardingQrController::class, 'scanCompany']);

    // ── Estimations ───────────────────────────────────────────────────────────
    Route::get('/employees/{employee}/balance', [PayrollCycleController::class, 'employeeBalance'])->whereNumber('employee'); // Plan 61
    Route::get('/employees/{employee}/ledger', [LedgerController::class, 'employeeLedger'])->whereNumber('employee'); // PA2-PAY-007
    Route::get('/employees/{employee}/daily-summary', [EstimationController::class, 'dailySummary'])->whereNumber('employee');
    Route::get('/employees/{employee}/quick-estimate', [EstimationController::class, 'quickEstimate'])->whereNumber('employee');
    Route::get('/employees/{employee}/receipt', [EstimationController::class, 'receipt'])->whereNumber('employee');

    // ── Self-service ──────────────────────────────────────────────────────────
    Route::get('/me/daily-summary', [MeController::class, 'dailySummary']);
    Route::get('/me/quick-estimate', [MeController::class, 'quickEstimate']);
    Route::get('/me/monthly-summary', [MeController::class, 'monthlySummary']);
    Route::get('/me/balance', [PayrollCycleController::class, 'myBalance']);
    Route::get('/me/ledger', [LedgerController::class, 'myLedger']); // PA2-PAY-007

    // ── Attendance ────────────────────────────────────────────────────────────
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance/anomalies', [AttendanceController::class, 'anomalies']);
    Route::get('/attendance/regularity', [AttendanceController::class, 'regularity']);
    Route::get('/attendance/monthly-report', [AttendanceController::class, 'monthlyReport']);
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::post('/attendance/corrections', [AttendanceController::class, 'requestCorrection']);
    Route::get('/attendance/corrections', [AttendanceController::class, 'corrections']);
    Route::put('/attendance/corrections/{correction}/approve', [AttendanceController::class, 'approveCorrection'])->whereNumber('correction');
    Route::put('/attendance/corrections/{correction}/reject', [AttendanceController::class, 'rejectCorrection'])->whereNumber('correction');
    Route::put('/attendance/{attendanceLog}', [AttendanceController::class, 'update'])->whereNumber('attendanceLog');
    Route::get('/attendance/{attendanceLog}/punch-photo', [AttendanceController::class, 'punchPhoto'])->whereNumber('attendanceLog')->name('attendance.punch-photo');

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
    // Plan 60 — double validation workflow
    Route::put('/salary-advances/{salaryAdvance}/manager-approve', [SalaryAdvanceController::class, 'managerApprove'])->whereNumber('salaryAdvance');
    Route::put('/salary-advances/{salaryAdvance}/mark-paid', [SalaryAdvanceController::class, 'markPaid'])->whereNumber('salaryAdvance');
    Route::put('/salary-advances/{salaryAdvance}/confirm-received', [SalaryAdvanceController::class, 'confirmReceived'])->whereNumber('salaryAdvance');

    // ── Module 3 — Payrolls ───────────────────────────────────────────────────
    Route::get('/payrolls', [PayrollController::class, 'index']);
    Route::post('/payrolls', [PayrollController::class, 'store']);
    Route::get('/payrolls/{payroll}', [PayrollController::class, 'show'])->whereNumber('payroll');
    Route::put('/payrolls/{payroll}', [PayrollController::class, 'update'])->whereNumber('payroll');
    Route::patch('/payrolls/{payroll}', [PayrollController::class, 'update'])->whereNumber('payroll');
    Route::put('/payrolls/{payroll}/validate', [PayrollController::class, 'validatePayroll'])->whereNumber('payroll');
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
    Route::post('/schedules/{schedule}/assign-employees', [ScheduleController::class, 'assignEmployees'])->whereNumber('schedule');
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->whereNumber('schedule');

    // ── Module 5 — Notifications ──────────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereNumber('notification');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->whereNumber('notification');
    Route::get('/notifications/stream', [NotificationStreamController::class, 'stream']);
    Route::post('/notifications/sse-token', [SseTokenController::class, 'issue']);

    // ── Module 5 (complement) — Company announcements (PA2-COMM-004) ──────────
    // PA2-COMM-011 — Moderation: publish/cancel a draft or scheduled announcement.
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->whereNumber('announcement');
    Route::post('/announcements/{announcement}/cancel', [AnnouncementController::class, 'cancel'])->whereNumber('announcement');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->whereNumber('announcement');

    // ── Module 7 — Projects & Tasks ───────────────────────────────────────────
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->whereNumber('project');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->whereNumber('project');

    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/tasks/today', [TaskController::class, 'today']);
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->whereNumber('task');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->whereNumber('task');
    Route::get('/tasks/{task}/comments', [TaskController::class, 'listComments'])->whereNumber('task');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'addComment'])->whereNumber('task');

    // ── Module 7 (complément) — Evaluations ──────────────────────────────────
    Route::get('/evaluations', [EvaluationController::class, 'index']);
    Route::post('/evaluations', [EvaluationController::class, 'store']);
    Route::get('/evaluations/{evaluation}', [EvaluationController::class, 'show'])->whereNumber('evaluation');
    Route::put('/evaluations/{evaluation}', [EvaluationController::class, 'update'])->whereNumber('evaluation');
    Route::patch('/evaluations/{evaluation}', [EvaluationController::class, 'update'])->whereNumber('evaluation');
    Route::put('/evaluations/{evaluation}/submit', [EvaluationController::class, 'submit'])->whereNumber('evaluation');
    Route::put('/evaluations/{evaluation}/acknowledge', [EvaluationController::class, 'acknowledge'])->whereNumber('evaluation');
    Route::delete('/evaluations/{evaluation}', [EvaluationController::class, 'destroy'])->whereNumber('evaluation');
});

// ── Kiosk — auth par X-Kiosk-Token ────────────────────────────────────────────
Route::middleware(['throttle:api'])->group(function (): void {
    Route::get('/kiosks/{deviceCode}/roster', [KioskController::class, 'roster']);
    Route::post('/kiosks/{deviceCode}/punch', [KioskController::class, 'punch']);
    Route::post('/kiosks/{deviceCode}/sync', [KioskController::class, 'sync']);
});
