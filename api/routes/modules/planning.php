<?php

use App\Http\Controllers\Api\V1\PlanningController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])->prefix('planning')->group(function (): void {
    Route::get('/weekly-optimization', [PlanningController::class, 'weeklyOptimization']);
    Route::get('/shift-rebalancing', [PlanningController::class, 'shiftRebalancing']);
});
