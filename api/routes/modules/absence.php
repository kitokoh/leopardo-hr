<?php

declare(strict_types=1);

use App\Support\Routing\ModuleRoutes;
use App\Modules\Absence\Interfaces\Api\V1\Controllers\AbsenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Absence Module Routes
|--------------------------------------------------------------------------
| Mounted inside Route::prefix('v1') in api.php.
| Full paths: /api/v1/absences/...
*/

ModuleRoutes::tenantModule('absences', function (): void {
        Route::get('/', [AbsenceController::class, 'index']);
        Route::post('/', [AbsenceController::class, 'store']);
        Route::get('/{absence}', [AbsenceController::class, 'show'])->whereNumber('absence');
        // PA2-MOB-006: download the supporting document attached to a request.
        Route::get('/{absence}/proof', [AbsenceController::class, 'downloadProof'])->whereNumber('absence');
        // #4930 : action métier → POST (convention REST). Alias PUT déprécié
        // conservé pour rétrocompatibilité Flutter.
        Route::post('/{absence}/approve', [AbsenceController::class, 'approve'])->whereNumber('absence');
        Route::post('/{absence}/reject', [AbsenceController::class, 'reject'])->whereNumber('absence');
        Route::put('/{absence}/approve', [AbsenceController::class, 'approve'])->whereNumber('absence');
        Route::put('/{absence}/reject', [AbsenceController::class, 'reject'])->whereNumber('absence');
        // #4933 : modification d'une demande en attente (dates/raison).
        Route::put('/{absence}', [AbsenceController::class, 'update'])->whereNumber('absence');
        Route::delete('/{absence}', [AbsenceController::class, 'destroy'])->whereNumber('absence');
    });
