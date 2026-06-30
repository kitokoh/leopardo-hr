<?php

/**
 * Routes Edge — déploiement et gestion des nœuds offline.
 *
 * Publiques (pas d'auth) :
 *   GET  /edge/install.sh
 *   GET  /edge/download/docker-compose.yml
 *   GET  /edge/download/env-example
 *   GET  /edge/license-public-key
 *
 * Nœud Edge (Bearer EDGE_TOKEN) :
 *   POST /edge/heartbeat
 *   GET  /edge/health
 *
 * Super-admin :
 *   GET    /platform/edge/nodes
 *   POST   /platform/edge/nodes/{id}/sync
 *   DELETE /platform/edge/nodes/{id}
 */

use App\Http\Controllers\Api\V1\EdgeController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Routes publiques — download & clé publique
// ---------------------------------------------------------------------------
Route::prefix('edge')->group(function (): void {

    // Script d'installation curl | bash
    Route::get('/install.sh', [EdgeController::class, 'installScript'])
        ->name('edge.install');

    // Téléchargements
    Route::prefix('download')->group(function (): void {
        Route::get('/docker-compose.yml', [EdgeController::class, 'downloadDockerCompose'])
            ->name('edge.download.compose');

        Route::get('/env-example', [EdgeController::class, 'downloadEnvExample'])
            ->name('edge.download.env');
    });

    // Clé publique RS256 pour vérification licences
    Route::get('/license-public-key', [EdgeController::class, 'licensePublicKey'])
        ->name('edge.license.public-key');

    // Heartbeat nœud → cloud (auth via middleware edge.token à ajouter)
    Route::post('/heartbeat', [EdgeController::class, 'heartbeat'])
        ->middleware(['throttle:edge-heartbeat'])
        ->name('edge.heartbeat');

    // Health du nœud (appelé par le docker healthcheck)
    Route::get('/health', function () {
        return response()->json([
            'status'     => 'ok',
            'edge'       => true,
            'timestamp'  => now()->toIso8601String(),
        ]);
    })->name('edge.health');
});

// ---------------------------------------------------------------------------
// Routes platform super-admin — gestion des nœuds
// (montées dans le groupe platform de api.php)
// ---------------------------------------------------------------------------
Route::middleware(['auth:super_admin_api', 'throttle:platform-sensitive'])
    ->prefix('platform/edge')
    ->name('platform.edge.')
    ->group(function (): void {
        Route::get('/nodes', [EdgeController::class, 'listNodes'])
            ->name('nodes.index');

        Route::post('/nodes/{id}/sync', [EdgeController::class, 'forceSync'])
            ->whereNumber('id')
            ->name('nodes.sync');

        Route::delete('/nodes/{id}', [EdgeController::class, 'revokeNode'])
            ->whereNumber('id')
            ->name('nodes.revoke');
    });
