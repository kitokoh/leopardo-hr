<?php

/**
 * Routes du module Surveillance Caméras (APV L.08).
 *
 * Ce fichier est chargé depuis routes/api.php à l'intérieur du groupe /v1.
 * Toutes les routes authentifiées passent par :
 *   - throttle (60 req/min)
 *   - auth:sanctum
 *   - tenant (résolution company + garde-fous statut/archive)
 *   - module.cameras (companies.features.cameras=true)
 *
 * Référence : docs/vision/Leopardo_RH_Camera_Complet+1.pdf, section 6.
 */

use App\Http\Controllers\Api\V1\Cameras\CameraAccessLogController;
use App\Http\Controllers\Api\V1\Cameras\CameraAccessTokenController;
use App\Http\Controllers\Api\V1\Cameras\CameraController;
use App\Http\Controllers\Api\V1\Cameras\CameraPermissionController;
use App\Http\Controllers\Api\V1\Cameras\InternalCameraTokenController;
use App\Http\Controllers\Api\V1\Cameras\PublicCameraViewerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1', 'auth:sanctum', 'tenant', 'module.cameras'])
    ->prefix('cameras')
    ->group(function (): void {
        Route::get('/', [CameraController::class, 'index']);
        Route::post('/', [CameraController::class, 'store']);
        Route::post('/test-rtsp', [CameraController::class, 'testRtsp']);

        Route::get('/{camera}', [CameraController::class, 'show'])->whereNumber('camera');
        Route::put('/{camera}', [CameraController::class, 'update'])->whereNumber('camera');
        Route::patch('/{camera}', [CameraController::class, 'update'])->whereNumber('camera');
        Route::delete('/{camera}', [CameraController::class, 'destroy'])->whereNumber('camera');
        Route::get('/{camera}/stream-token', [CameraController::class, 'streamToken'])->whereNumber('camera');

        // Access tokens (partage tiers)
        Route::get('/{camera}/access-tokens', [CameraAccessTokenController::class, 'index'])->whereNumber('camera');
        Route::post('/{camera}/access-tokens', [CameraAccessTokenController::class, 'store'])->whereNumber('camera');
        Route::delete('/{camera}/access-tokens/{token}', [CameraAccessTokenController::class, 'destroy'])
            ->whereNumber(['camera', 'token']);

        // Permissions internes (Principal only)
        Route::get('/{camera}/permissions', [CameraPermissionController::class, 'index'])->whereNumber('camera');
        Route::post('/{camera}/permissions', [CameraPermissionController::class, 'store'])->whereNumber('camera');
        Route::delete('/{camera}/permissions/{permission}', [CameraPermissionController::class, 'destroy'])
            ->whereNumber(['camera', 'permission']);

        // Logs
        Route::get('/{camera}/access-logs', [CameraAccessLogController::class, 'index'])->whereNumber('camera');
    });

// Endpoint interne appelé par MediaMTX — auth par Bearer secret dédié.
// Hors du groupe ci-dessus car pas besoin de Sanctum ni du tenant middleware.
Route::middleware(['throttle:600,1'])
    ->get('/internal/camera-token/verify', [InternalCameraTokenController::class, 'verify']);

// Viewer public avec token tiers (?t=<opaque>). Pas d'auth utilisateur.
Route::middleware(['throttle:60,1'])
    ->get('/view/cam', PublicCameraViewerController::class);
