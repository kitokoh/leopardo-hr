<?php

/**
 * Routes du module RH (pointage, employes, invitations, estimations, kiosque).
 *
 * APV L.08 — Un module = un route group Laravel.
 * Ce fichier est charge depuis routes/api.php a l'interieur du groupe /v1.
 *
 * Convention :
 *  - Toutes les routes ici requierent le middleware du module (gate companies.features.rh
 *    sauf si le module RH est toujours actif, ce qui est le cas par defaut).
 *  - Aucune route ici n'importe directement un autre module. La communication
 *    inter-module passe par le core (evenements / services dedies).
 */

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\BiometricEnrollmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EstimationController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\KioskController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1', 'auth:sanctum', 'tenant'])->group(function (): void {
    // Employes — CRUD manager/RH
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee');
    Route::patch('/employees/{employee}', [EmployeeController::class, 'update'])->whereNumber('employee');
    Route::post('/employees/{employee}/archive', [EmployeeController::class, 'archive'])->whereNumber('employee');

    // Estimations par employe (manager/RH)
    Route::get('/employees/{employee}/daily-summary', [EstimationController::class, 'dailySummary'])->whereNumber('employee');
    Route::get('/employees/{employee}/quick-estimate', [EstimationController::class, 'quickEstimate'])->whereNumber('employee');
    Route::get('/employees/{employee}/receipt', [EstimationController::class, 'receipt'])->whereNumber('employee');

    // Self-service employe (APV L.02 — mobile-first)
    Route::get('/me/daily-summary', [MeController::class, 'dailySummary']);
    Route::get('/me/quick-estimate', [MeController::class, 'quickEstimate']);
    Route::get('/me/monthly-summary', [MeController::class, 'monthlySummary']);

    // Pointage
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance', [AttendanceController::class, 'index']);

    // Invitations (manager/RH)
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend']);

    // Biometric enrollment + kiosques (register)
    Route::get('/biometric-enrollment-requests', [BiometricEnrollmentController::class, 'index']);
    Route::post('/biometric-enrollment-requests/{id}/approve', [BiometricEnrollmentController::class, 'approve']);
    Route::post('/biometric-enrollment-requests/{id}/reject', [BiometricEnrollmentController::class, 'reject']);
    Route::post('/kiosks', [KioskController::class, 'register']);
});

// Kiosque — auth par X-Kiosk-Token, pas sanctum
Route::get('/kiosks/{deviceCode}/roster', [KioskController::class, 'roster']);
Route::post('/kiosks/{deviceCode}/punch', [KioskController::class, 'punch']);
Route::post('/kiosks/{deviceCode}/sync', [KioskController::class, 'sync']);
