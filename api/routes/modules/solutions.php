<?php

declare(strict_types=1);

use App\Core\Solutions\Interfaces\Api\V1\SolutionSurveyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Solutions Sectorielles — Routes publiques (vitrine)
|--------------------------------------------------------------------------
| Mounted inside Route::prefix('v1') in api.php.
| Full paths: /api/v1/solutions/...
|
| Public sans auth (pré-qualification avant inscription) : le survey ne
| manipule aucune donnée tenant ni secret. Throttle strict pour éviter
| l'abus du moteur de règles (purement CPU, mais gratuit = protégé).
*/

Route::middleware(['throttle:10,1'])
    ->prefix('solutions')
    ->group(function (): void {
        Route::get('/', [SolutionSurveyController::class, 'index']);
        Route::get('/{code}/survey', [SolutionSurveyController::class, 'questions']);
        Route::post('/{code}/survey', [SolutionSurveyController::class, 'suggest']);
        Route::get('/{code}/pack', [SolutionSurveyController::class, 'downloadPack']);
    });
