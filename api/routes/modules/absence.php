<?php

declare(strict_types=1);

use App\Modules\Absence\Interfaces\Api\V1\Controllers\AbsenceController;
use App\Modules\Absence\Interfaces\Api\V1\Controllers\LeavePolicyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Absence Module Routes
|--------------------------------------------------------------------------
| Mounted inside Route::prefix('v1') in api.php.
| Full paths: /api/v1/absences/...
*/

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])
    ->prefix('absences')
    ->group(function (): void {
        Route::get('/', [AbsenceController::class, 'index']);
        Route::post('/', [AbsenceController::class, 'store']);
        Route::get('/{absence}', [AbsenceController::class, 'show'])->whereNumber('absence');
        // PA2-MOB-006: download the supporting document attached to a request.
        Route::get('/{absence}/proof', [AbsenceController::class, 'downloadProof'])->whereNumber('absence');
        Route::put('/{absence}/approve', [AbsenceController::class, 'approve'])->whereNumber('absence');
        Route::post('/{absence}/approve', [AbsenceController::class, 'approve'])->whereNumber('absence');
        Route::put('/{absence}/reject', [AbsenceController::class, 'reject'])->whereNumber('absence');
        Route::post('/{absence}/reject', [AbsenceController::class, 'reject'])->whereNumber('absence');
        Route::delete('/{absence}', [AbsenceController::class, 'destroy'])->whereNumber('absence');
    });

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])
    ->prefix('employees/{employeeId}/leave-balances')
    ->group(function (): void {
        Route::get('/', [LeavePolicyController::class, 'balances']);
    });
