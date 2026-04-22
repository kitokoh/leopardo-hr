<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BiometricEnrollmentController;
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

    // Auth (core, hors module)
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
    });

    // APV L.08 — Modules Leopardo, chaque module a son propre route group.
    // RH est le module de base : toujours charge. Les autres modules Phase 2
    // (finance, cameras, muhasebe, leo_ai) seront inclus ici derriere un gate
    // companies.features lors de leur implementation.
    require __DIR__.'/modules/rh.php';

    // Platform (super-admin, hors module)
    Route::middleware(['auth:super_admin_api'])->prefix('platform')->group(function (): void {
        Route::get('/auth/me', [PlatformAuthController::class, 'me']);
        Route::post('/auth/logout', [PlatformAuthController::class, 'logout']);
        Route::get('/companies', [PlatformCompanyController::class, 'index']);
        Route::post('/companies', [PlatformCompanyController::class, 'store']);
    });
});
