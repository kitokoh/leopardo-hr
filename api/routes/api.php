<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'version' => '4.1.4',
        ]);
    });

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::patch('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::post('/employees/{employee}/archive', [EmployeeController::class, 'archive']);

        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('/attendance/today', [AttendanceController::class, 'today']);
        Route::get('/attendance', [AttendanceController::class, 'index']);
    });
});
