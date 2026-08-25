<?php

declare(strict_types=1);

/**
 * Routes géo — module Attendance (ADR-0016 Phase 3, issue #5354).
 *
 * Surface de pointage géo consolidée sous /api/v1/attendance/*.
 * Phase 5 (#5356) : les alias /smart-attendance/* ont été supprimés
 * (contrat unique /attendance/*, vérifié mobile + web).
 */

use App\Modules\Attendance\Interfaces\Api\V1\AttendanceModeController;
use App\Modules\Attendance\Interfaces\Api\V1\GeoAttendanceController;
use App\Modules\Attendance\Interfaces\Api\V1\GeoSessionController;
use App\Modules\Attendance\Interfaces\Api\V1\AttendanceDayClosureController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/attendance')
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
            Route::get('/geo-sessions', [GeoSessionController::class, 'index']);
            Route::get('/geo-sessions/{id}', [GeoSessionController::class, 'show'])->whereNumber('id');
            Route::post('/geo-sessions/{id}/approve', [GeoSessionController::class, 'approve'])->whereNumber('id');
            Route::post('/geo-sessions/{id}/reject', [GeoSessionController::class, 'reject'])->whereNumber('id');
            Route::get('/dashboard', [GeoSessionController::class, 'dashboard']);

            // Config mode entreprise (lecture manager/RH)
            Route::get('/mode-settings', [AttendanceModeController::class, 'getCompanySettings']);

            // Préférence d'un employé (lecture manager)
            Route::get('/employees/{employeeId}/preference', [AttendanceModeController::class, 'employeePreference'])->whereNumber('employeeId');

            // ── Fermeture de journée (#5265) — verrouillage + validation ────────
            Route::get('/day-closures', [AttendanceDayClosureController::class, 'index']);
            Route::post('/day-closures', [AttendanceDayClosureController::class, 'store']);
            Route::post('/day-closures/{id}/validate', [AttendanceDayClosureController::class, 'markValidated'])->whereNumber('id');
            Route::delete('/day-closures/{id}', [AttendanceDayClosureController::class, 'destroy'])->whereNumber('id');
        });

        // ── Config mode entreprise (modification — principal uniquement) ──────
        Route::middleware('api.manager:principal')->group(function (): void {
            Route::put('/mode-settings', [AttendanceModeController::class, 'updateCompanySettings']);
        });
    });
