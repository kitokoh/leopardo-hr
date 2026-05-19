<?php

/**
 * Routes Dashboard, Notifications & Exports — Sprint 15-16.
 */

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])->group(function (): void {

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity']);
    Route::get('/dashboard/kpi', [DashboardController::class, 'kpi']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

    // Exports
    Route::get('/export/employees', [ExportController::class, 'employees']);
    Route::get('/export/attendance', [ExportController::class, 'attendance']);
    Route::get('/export/pay-slips', [ExportController::class, 'paySlips']);
    Route::get('/export/absences', [ExportController::class, 'absences']);
    Route::get('/export/training', [ExportController::class, 'training']);
    Route::get('/export/contracts', [ExportController::class, 'contracts']);
    Route::get('/export/vehicles', [ExportController::class, 'vehicles']);
    Route::get('/export/history', [ExportController::class, 'history']);
});
