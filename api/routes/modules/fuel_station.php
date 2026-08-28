<?php

declare(strict_types=1);

/**
 * Routes Module FuelStation — Issue #5795 (FUEL-001).
 *
 * RBAC : lecture du manifest pour les managers `principal`/`rh` ; activation
 * réservée `principal` (FuelStationManifestController::activate). Isolation
 * tenant : flags sur `companies.features` (tenant courant via TenantMiddleware).
 */

use App\Modules\FuelStation\Interfaces\Api\V1\Controllers\FuelStationManifestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('fuel-station')
    ->group(function (): void {

        Route::middleware('api.manager:principal,rh')->group(function (): void {
            Route::get('/manifest', [FuelStationManifestController::class, 'manifest']);
            Route::post('/activate', [FuelStationManifestController::class, 'activate']);
        });
    });
