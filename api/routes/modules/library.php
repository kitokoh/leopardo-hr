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

use App\Modules\Library\Interfaces\Api\V1\Controllers\LibraryPurchaseController;
use App\Modules\Library\Interfaces\Api\V1\Controllers\LibraryReadingProgressController;
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
    Route::post('/resources/{resource}/file', [LibraryResourceController::class, 'uploadFile'])->whereNumber('resource');

    // Achat à l'unité / vente en gros (BC-38, #9238). `confirm` est idempotent
    // et ne fait jamais confiance au client : le paiement est relu en base.
    Route::get('/purchases', [LibraryPurchaseController::class, 'index']);
    Route::post('/resources/{resource}/purchases', [LibraryPurchaseController::class, 'store'])->whereNumber('resource');
    Route::post('/purchases/{purchase}/confirm', [LibraryPurchaseController::class, 'confirm'])->whereNumber('purchase');
    Route::get('/resources/{resource}/access', [LibraryPurchaseController::class, 'access'])->whereNumber('resource');
    Route::get('/resources/{resource}/download', [LibraryPurchaseController::class, 'download'])->whereNumber('resource');

    // Progression de lecture persistée côté serveur (par employé, par tenant).
    // Le slug identifie la ressource ; l'employé vient de la session.
    Route::get('/progress', [LibraryReadingProgressController::class, 'index']);
    Route::get('/progress/{slug}', [LibraryReadingProgressController::class, 'show']);
    Route::put('/progress/{slug}', [LibraryReadingProgressController::class, 'update']);
});
