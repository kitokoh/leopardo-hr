<?php

declare(strict_types=1);

/**
 * Routes — Module SmartAttendance
 *
 * Architecture multi-app Leopardo :
 *   - App Employee   → /api/v1/smart-attendance/* (employee self-service + événements GPS)
 *   - App Manager/RH → /api/v1/smart-attendance/sessions/* (validation)
 *   - Principal       → /api/v1/smart-attendance/mode-settings (config entreprise)
 */

use App\Modules\SmartAttendance\Interfaces\Api\V1\AttendanceModeController;
use App\Modules\SmartAttendance\Interfaces\Api\V1\GeoAttendanceController;
use App\Modules\SmartAttendance\Interfaces\Api\V1\GeoSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/smart-attendance')
    ->middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])
    ->group(function (): void {

        // ── Config mode (tous les employés authentifiés) ──────────────────────
        Route::get('/config', [AttendanceModeController::class, 'config']);

        // ── Préférence employé (self-service) ─────────────────────────────────
        Route::put('/preferences', [AttendanceModeController::class, 'updatePreference']);

        // ── Événements GPS (mobile employé) ───────────────────────────────────
        Route::post('/geo-events', [GeoAttendanceController::class, 'event']);

        // ── Mes sessions GPS (employé connecté) ───────────────────────────────
        Route::get('/my-sessions', [GeoSessionController::class, 'mySessions']);

        // ── Dashboard + validation manager/RH ────────────────────────────────
        Route::middleware('api.manager:rh,principal')->group(function (): void {
            Route::get('/sessions',                [GeoSessionController::class, 'index']);
            Route::get('/sessions/{id}',           [GeoSessionController::class, 'show'])->whereNumber('id');
            Route::post('/sessions/{id}/approve',  [GeoSessionController::class, 'approve'])->whereNumber('id');
            Route::post('/sessions/{id}/reject',   [GeoSessionController::class, 'reject'])->whereNumber('id');
            Route::get('/dashboard',               [GeoSessionController::class, 'dashboard']);

            // Config mode entreprise (lecture manager/RH)
            Route::get('/mode-settings', [AttendanceModeController::class, 'getCompanySettings']);

            // Préférence d'un employé (lecture manager)
            Route::get('/employees/{employeeId}/preference', [AttendanceModeController::class, 'employeePreference'])->whereNumber('employeeId');
        });

        // ── Config mode entreprise (modification — principal uniquement) ──────
        Route::middleware('api.manager:principal')->group(function (): void {
            Route::put('/mode-settings', [AttendanceModeController::class, 'updateCompanySettings']);
        });
    });
