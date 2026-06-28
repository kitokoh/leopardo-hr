<?php

declare(strict_types=1);

use App\Modules\Absence\Interfaces\Api\V1\Controllers\AbsenceController;
use App\Modules\Absence\Interfaces\Api\V1\Controllers\LeavePolicyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Absence Module Routes
|--------------------------------------------------------------------------
| Mounted under the api.php route group (auth:sanctum + tenant middleware).
*/

Route::prefix('v1/absences')->group(function () {
    Route::get('/',           [AbsenceController::class, 'index']);
    Route::post('/',          [AbsenceController::class, 'store']);
    Route::get('/{absence}',  [AbsenceController::class, 'show']);
    Route::post('/{absence}/approve', [AbsenceController::class, 'approve']);
    Route::post('/{absence}/reject',  [AbsenceController::class, 'reject']);
});

Route::prefix('v1/employees/{employeeId}/leave-balances')->group(function () {
    Route::get('/', [LeavePolicyController::class, 'balances']);
});
