<?php

use App\Modules\EdgeSync\Interfaces\Api\V1\EdgeNodeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EdgeSync API Routes
|--------------------------------------------------------------------------
|
| Two route groups:
|
|  1. Admin/Dashboard routes — Sanctum auth, company-scoped
|     Consumed by the web dashboard and admin to manage Edge nodes.
|
|  2. Edge node machine routes — edge_token bearer, rate-limited
|     Consumed by the Edge daemon running on the client's local server.
|
*/

// ── Admin / Dashboard Routes ──────────────────────────────────────────────
Route::prefix('api/v1/edge')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        Route::get('/', [EdgeNodeController::class, 'index']);
        Route::post('/', [EdgeNodeController::class, 'store']);
        Route::get('/{nodeId}', [EdgeNodeController::class, 'show']);
        Route::post('/{nodeId}/sync', [EdgeNodeController::class, 'sync']);
        Route::post('/{nodeId}/license', [EdgeNodeController::class, 'issueLicense']);
    });

// ── Edge Node Machine Routes ──────────────────────────────────────────────
Route::prefix('api/v1/edge-node')
    ->middleware(['api', 'throttle:300,1'])
    ->group(function () {
        Route::post('/{nodeId}/push', [EdgeNodeController::class, 'pushFromEdge']);
        Route::get('/{nodeId}/pull', [EdgeNodeController::class, 'pullDelta']);
        Route::post('/{nodeId}/heartbeat', [EdgeNodeController::class, 'heartbeat']);
        Route::post('/validate-license', [EdgeNodeController::class, 'validateLicense']);
    });
