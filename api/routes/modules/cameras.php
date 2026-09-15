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

use App\Http\Middleware\Cameras\EnsureMediamtxSecretMiddleware;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\CameraAccessLogController;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\CameraAccessTokenController;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\CameraAlertController;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\CameraController;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\CameraEventController;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\CameraPermissionController;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\InternalCameraEventController;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\InternalCameraTokenController;
use App\Modules\Cameras\Interfaces\Api\V1\Controllers\PublicCameraViewerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.cameras', 'api.manager'])
    ->prefix('cameras')
    ->group(function (): void {
        Route::get('/', [CameraController::class, 'index']);
        Route::post('/', [CameraController::class, 'store']);
        Route::post('/test-rtsp', [CameraController::class, 'testRtsp']);

        // Événements & alertes (#7427) — déclarés AVANT le joker /{camera}
        // (défense en profondeur : {camera} est déjà borné aux entiers).
        Route::get('/events', [CameraEventController::class, 'index']);
        Route::get('/alerts', [CameraAlertController::class, 'index']);
        Route::post('/alerts/{alert}/acknowledge', [CameraAlertController::class, 'acknowledge'])
            ->whereNumber('alert');
        Route::post('/alerts/{alert}/resolve', [CameraAlertController::class, 'resolve'])
            ->whereNumber('alert');

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

// Ingestion des événements de la chaîne vidéo (#7427) — même secret partagé
// MediaMTX (middleware dédié), toujours hors Sanctum/tenant : machine-à-machine.
Route::middleware([EnsureMediamtxSecretMiddleware::class, 'throttle:600,1'])
    ->post('/internal/camera-events', [InternalCameraEventController::class, 'store']);

// Viewer public avec token tiers (?t=<opaque>). Pas d'auth utilisateur.
Route::middleware(['throttle:api'])
    ->get('/view/cam', [PublicCameraViewerController::class, '__invoke']);
