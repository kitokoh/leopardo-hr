<?php

declare(strict_types=1);

use App\Modules\EdgeSync\Interfaces\Api\V1\EdgeController;
use App\Modules\EdgeSync\Interfaces\Api\V1\EdgeDownloadController;
use App\Modules\EdgeSync\Interfaces\Api\V1\EdgeNodeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EdgeSync API Routes
|--------------------------------------------------------------------------
|
| Public routes (no auth):
|   - Download endpoints for Edge node installation
|   - License public key
|   - Edge heartbeat (machine-to-cloud)
|
| Admin routes (auth:sanctum):
|   - Node management CRUD
|
| Super-admin routes (auth:super_admin_api):
|   - Platform-level node overview and revocation
|
| Edge node machine routes (throttled):
|   - Sync push/pull, heartbeat, license validation
*/

// ── Public download endpoints ──────────────────────────────────────────────
Route::prefix('edge')->group(function (): void {
    Route::get('/install.sh', [EdgeDownloadController::class, 'installScript'])
        ->middleware('throttle:60,1');
    Route::get('/download/docker-compose.yml', [EdgeDownloadController::class, 'dockerCompose'])
        ->middleware('throttle:60,1');
    Route::get('/download/env-example', [EdgeController::class, 'downloadEnvExample'])
        ->middleware('throttle:60,1');
    Route::get('/license-public-key', [EdgeDownloadController::class, 'licensePublicKey'])
        ->middleware('throttle:120,1');
    Route::post('/heartbeat', [EdgeController::class, 'heartbeat'])
        ->middleware('throttle:300,1');
});

// ── Authenticated admin routes ─────────────────────────────────────────────
Route::prefix('v1/edge')
    ->middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api'])
    ->group(function (): void {
        Route::get('/', [EdgeNodeController::class, 'index']);
        Route::post('/', [EdgeNodeController::class, 'store']);
        Route::get('/{nodeId}', [EdgeNodeController::class, 'show']);
        Route::post('/{nodeId}/sync', [EdgeNodeController::class, 'sync']);
        Route::post('/{nodeId}/license', [EdgeNodeController::class, 'issueLicense']);
    });

// ── Edge node machine routes ───────────────────────────────────────────────
Route::prefix('v1/edge-node')
    ->middleware(['api', 'throttle:300,1'])
    ->group(function (): void {
        Route::post('/{nodeId}/push', [EdgeNodeController::class, 'pushFromEdge']);
        Route::get('/{nodeId}/pull', [EdgeNodeController::class, 'pullDelta']);
        Route::post('/{nodeId}/heartbeat', [EdgeNodeController::class, 'heartbeat']);
        Route::post('/validate-license', [EdgeNodeController::class, 'validateLicense']);
    });
