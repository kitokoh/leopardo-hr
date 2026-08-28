<?php

declare(strict_types=1);

use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FuelStation Module Routes (FUEL-005, issue #5799)
|--------------------------------------------------------------------------
| Montées dans Route::prefix('v1') via api.php.
| Chemins : /api/v1/fuel/...
| RBAC : CRUD shifts + affectations = manager ; /fuel/me/shifts = tout
| employé authentifié (scope employee_id dans le contrôleur).
*/

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('fuel')
    ->group(function (): void {
        // Self-service pompiste : ses propres affectations (fenêtre date_from/date_to).
        Route::get('/me/shifts', [FuelShiftController::class, 'myShifts']);

        Route::middleware('api.manager')->group(function (): void {
            Route::get('/shifts', [FuelShiftController::class, 'index']);
            Route::post('/shifts', [FuelShiftController::class, 'store']);
            Route::get('/shifts/{shift}', [FuelShiftController::class, 'show'])->whereUuid('shift');
            Route::put('/shifts/{shift}', [FuelShiftController::class, 'update'])->whereUuid('shift');
            Route::delete('/shifts/{shift}', [FuelShiftController::class, 'destroy'])->whereUuid('shift');
            Route::get('/shifts/{shift}/assignments', [FuelShiftController::class, 'assignments'])->whereUuid('shift');
            Route::post('/shifts/{shift}/assignments', [FuelShiftController::class, 'assign'])->whereUuid('shift');
            Route::delete('/shift-assignments/{assignment}', [FuelShiftController::class, 'cancelAssignment'])->whereUuid('assignment');
        });
    });
