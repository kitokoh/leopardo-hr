<?php

declare(strict_types=1);

use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelPresenceController;
use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FuelStation Module Routes (FUEL-005 #5799, FUEL-006 #5800)
|--------------------------------------------------------------------------
| Montées dans Route::prefix('v1') via api.php.
| Chemins : /api/v1/fuel/...
| RBAC : CRUD shifts + affectations + rostre présence = manager ;
| /fuel/me/* = tout employé authentifié (scope employee_id).
*/

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('fuel')
    ->group(function (): void {
        // Self-service pompiste : ses propres affectations (fenêtre date_from/date_to).
        Route::get('/me/shifts', [FuelShiftController::class, 'myShifts']);
        // FUEL-006 (#5800) : présence du pompiste pour une date (Y-m-d).
        Route::get('/me/presence', [FuelPresenceController::class, 'myPresence']);

        Route::middleware('api.manager')->group(function (): void {
            Route::get('/shifts', [FuelShiftController::class, 'index']);
            Route::post('/shifts', [FuelShiftController::class, 'store']);
            Route::get('/shifts/{shift}', [FuelShiftController::class, 'show'])->whereUuid('shift');
            Route::put('/shifts/{shift}', [FuelShiftController::class, 'update'])->whereUuid('shift');
            Route::delete('/shifts/{shift}', [FuelShiftController::class, 'destroy'])->whereUuid('shift');
            Route::get('/shifts/{shift}/assignments', [FuelShiftController::class, 'assignments'])->whereUuid('shift');
            Route::post('/shifts/{shift}/assignments', [FuelShiftController::class, 'assign'])->whereUuid('shift');
            Route::delete('/shift-assignments/{assignment}', [FuelShiftController::class, 'cancelAssignment'])->whereUuid('assignment');
            // FUEL-006 (#5800) : rostre de présence du shift pour une date (Y-m-d).
            Route::get('/shifts/{shift}/presence', [FuelPresenceController::class, 'shiftPresence'])->whereUuid('shift');
        });
    });
