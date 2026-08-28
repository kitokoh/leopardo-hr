<?php

/**
 * Routes FuelStation (issues #5795, #5796, #5797, #5798).
 *
 * Tenant-scoped, réservées aux managers principal/rh (RBAC_ROUTE_MATRIX.md).
 * Le CRM commercial plateforme (Platform/Marketing) n'est jamais modifié.
 */

use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelStationManifestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal,rh'])
    ->prefix('fuelstation')
    ->group(function (): void {
        Route::get('/manifest', [FuelStationManifestController::class, 'manifest']);
        Route::post('/activate', [FuelStationManifestController::class, 'activate']);
        Route::get('/status', [FuelStationManifestController::class, 'status']);
    });
