<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\BiometricEnrollmentController;
use App\Http\Controllers\Api\V1\EstimationController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\KioskController;
use App\Http\Controllers\Api\V1\PlatformAuthController;
use App\Http\Controllers\Web\PlatformCompanyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => '4.1.4',
        ]);
    });

    Route::middleware(['throttle:10,1'])->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/platform/auth/login', [PlatformAuthController::class, 'login']);
    });

    Route::middleware(['throttle:60,1', 'auth:sanctum', 'tenant'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/biometric-enrollment', [BiometricEnrollmentController::class, 'myStatus']);
        Route::post('/auth/biometric-enrollment', [BiometricEnrollmentController::class, 'store']);

        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::patch('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::post('/employees/{employee}/archive', [EmployeeController::class, 'archive']);

        Route::get('/employees/{employee}/daily-summary', [EstimationController::class, 'dailySummary']);
        Route::get('/employees/{employee}/quick-estimate', [EstimationController::class, 'quickEstimate']);
        Route::get('/employees/{employee}/receipt', [EstimationController::class, 'receipt']);

        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::get('/attendance', [AttendanceController::class, 'index']);

        Route::get('/biometric-enrollment-requests', [BiometricEnrollmentController::class, 'index']);
        Route::post('/biometric-enrollment-requests/{id}/approve', [BiometricEnrollmentController::class, 'approve']);
        Route::post('/biometric-enrollment-requests/{id}/reject', [BiometricEnrollmentController::class, 'reject']);
        Route::post('/kiosks', [KioskController::class, 'register']);
    });

    Route::get('/kiosks/{deviceCode}/roster', [KioskController::class, 'roster']);
    Route::post('/kiosks/{deviceCode}/punch', [KioskController::class, 'punch']);
    Route::post('/kiosks/{deviceCode}/sync', [KioskController::class, 'sync']);

    Route::middleware(['auth:super_admin_api'])->prefix('platform')->group(function (): void {
        Route::get('/auth/me', [PlatformAuthController::class, 'me']);
        Route::post('/auth/logout', [PlatformAuthController::class, 'logout']);
        Route::get('/companies', [PlatformCompanyController::class, 'index']);
        Route::post('/companies', [PlatformCompanyController::class, 'store']);
    });
});
