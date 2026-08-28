<?php

/**
 * Routes Dashboard, Notifications & Exports — Sprint 15-16.
 *
 * Dashboard & Exports require manager access (api.manager middleware).
 * Notifications are available to all authenticated employees.
 */

use App\Modules\Attendance\Interfaces\Api\V1\AttendanceExportController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\DashboardController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\ExportController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\NotificationController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\RoleAssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {

    // Dashboard — managers only
    Route::middleware('api.manager')->group(function (): void {
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity']);
        Route::get('/dashboard/kpi', [DashboardController::class, 'kpi']);
        Route::get('/dashboard/manager-digest', [DashboardController::class, 'managerDigest']);
        
        // Developer API Tokens
        Route::get('/api-tokens', [\App\Modules\HR\Interfaces\Api\V1\Controllers\ApiTokenController::class, 'index']);
        Route::post('/api-tokens', [\App\Modules\HR\Interfaces\Api\V1\Controllers\ApiTokenController::class, 'store']);
        Route::delete('/api-tokens/{tokenId}', [\App\Modules\HR\Interfaces\Api\V1\Controllers\ApiTokenController::class, 'destroy']);
    });

    // Notifications — all authenticated employees
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    // DEPRECIES (compat) : la convention canonique de marquage est
    //   POST /notifications/read-all et PATCH /notifications/{id}/read
    // definies dans routes/modules/rh.php (consommees par web + mobile,
    // issue #2674 — un PR antérieur a tenté PUT et a été rejeté).
    // Ces alias POST/PATCH restent actifs et testes (NotificationControllerTest),
    // mais ne doivent plus etre utilises par les nouveaux clients.
    // DEPRECATED (#4932) : doublons historiques — le contrat canonique est
    // POST /notifications/read-all + PATCH /notifications/{id}/read
    // (routes/modules/rh.php, #2674). Conservés pour compatibilité.
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

    // Exports — managers only (principal, rh, comptable)
    Route::middleware('api.manager:principal,rh,comptable')->group(function (): void {
        Route::get('/export/employees', [ExportController::class, 'employees']);
        Route::get('/export/attendance', [ExportController::class, 'attendance']);
        Route::get('/export/attendance/monthly', [AttendanceExportController::class, 'attendanceMonthly']);
        Route::get('/export/pay-slips', [ExportController::class, 'paySlips']);
        Route::get('/export/absences', [ExportController::class, 'absences']);
        Route::get('/export/training', [ExportController::class, 'training']);
        Route::get('/export/contracts', [ExportController::class, 'contracts']);
        Route::get('/export/vehicles', [ExportController::class, 'vehicles']);
        Route::get('/export/history', [ExportController::class, 'history']);
        
        // Accounting specific exports
        Route::get('/export/payroll-journal', [ExportController::class, 'accountingJournal']);
        Route::get('/export/payroll-ledger', [ExportController::class, 'accountingLedger']);
        Route::get('/export/accounting-od', [ExportController::class, 'accountingOD']);
    });

    // Dashboard data filtered by manager_role
    Route::middleware('api.manager:principal')->group(function (): void {
        Route::get('/dashboard/admin', [DashboardController::class, 'adminSummary']);
        Route::get('/company/team-roles', [RoleAssignmentController::class, 'teamRoles']);
        Route::post('/employees/{employee}/assign-role', [RoleAssignmentController::class, 'assign']);
    });

    Route::middleware('api.manager:rh,principal')->group(function (): void {
        Route::get('/dashboard/rh', [DashboardController::class, 'rhSummary']);
    });

    Route::middleware('api.manager:comptable,principal')->group(function (): void {
        Route::get('/dashboard/comptable', [DashboardController::class, 'comptableSummary']);
    });

    Route::middleware('api.manager:marketing,principal')->group(function (): void {
        Route::get('/dashboard/marketing', [DashboardController::class, 'marketingSummary']);
    });
});
