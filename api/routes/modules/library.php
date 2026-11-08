<?php

/**
 * Routes privées du module Library (gestion par un tenant).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * La pile de middlewares tenant (throttle:api, auth:sanctum, token.refresh,
 * tenant, throttle:api-plan) vit en UN seul endroit :
 * `App\Support\Routing\ModuleRoutes` (issue #7959). On ne la recopie pas ici.
 *
 * Workflow éditorial : brouillon → en attente → publié → archivé. Aucune
 * publication automatique. Isolation tenant garantie par le scope
 * `BelongsToCompany` et la policy `LibraryResourcePolicy`.
 */

use App\Modules\Library\Interfaces\Api\V1\Controllers\LibraryResourceController;
use App\Support\Routing\ModuleRoutes;
use Illuminate\Support\Facades\Route;

ModuleRoutes::tenantModule('library', function (): void {
    Route::get('/resources', [LibraryResourceController::class, 'index']);
    Route::post('/resources', [LibraryResourceController::class, 'store']);
    Route::get('/resources/{resource}', [LibraryResourceController::class, 'show'])->whereNumber('resource');
    Route::put('/resources/{resource}', [LibraryResourceController::class, 'update'])->whereNumber('resource');
    Route::delete('/resources/{resource}', [LibraryResourceController::class, 'destroy'])->whereNumber('resource');
    Route::post('/resources/{resource}/publish', [LibraryResourceController::class, 'publish'])->whereNumber('resource');
    Route::post('/resources/{resource}/unpublish', [LibraryResourceController::class, 'unpublish'])->whereNumber('resource');
    Route::post('/resources/{resource}/archive', [LibraryResourceController::class, 'archive'])->whereNumber('resource');
});
