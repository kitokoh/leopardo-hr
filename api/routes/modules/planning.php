<?php

use App\Modules\Planning\Interfaces\Api\V1\Controllers\PlanningController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager'])->prefix('planning')->group(function (): void {
    // Collection racine : le chemin qu'appelle l'écran Planning. Déclarée
    // avant les sous-chemins pour rester la route par défaut du préfixe.
    Route::get('/', [PlanningController::class, 'index']);
    Route::get('/weekly-optimization', [PlanningController::class, 'weeklyOptimization']);
    Route::get('/shift-rebalancing', [PlanningController::class, 'shiftRebalancing']);
});
