<?php

/**
 * Routes du module Connecteurs (BC-14 INTEGRATION, #9848).
 *
 * Onglet « Connecteurs » côté TENANT : un client branche ses surfaces externes
 * (site WordPress, page produit Facebook, boutique Shopify/Instagram/TikTok
 * Shop…) et retrouve au même endroit les intégrations qu'il utilisait déjà.
 *
 * RBAC :
 *   - lecture (catalogue, liste, index des intégrations) : tout manager ;
 *   - écriture (créer, modifier, activer, supprimer, tester) : `principal`
 *     uniquement — connecter un compte externe engage l'entreprise, comme les
 *     profils d'encaissement (#7727).
 *
 * Isolation : `ConnectorConnection` porte `BelongsToCompany` ; le scope tenant
 * est posé par la pile `ModuleRoutes::tenantModule`.
 */

use App\Modules\Connectors\Interfaces\Api\V1\Controllers\TenantConnectorController;
use App\Support\Routing\ModuleRoutes;
use Illuminate\Support\Facades\Route;

ModuleRoutes::tenantModule('connectors', function (): void {
    // Lecture — tout manager du tenant.
    Route::get('/catalog', [TenantConnectorController::class, 'catalog']);
    Route::get('/connections', [TenantConnectorController::class, 'index']);
    Route::get('/overview', [TenantConnectorController::class, 'overview']);

    // Écriture — principal uniquement.
    Route::middleware('api.manager:principal')->group(function (): void {
        Route::post('/connections', [TenantConnectorController::class, 'store']);
        Route::patch('/connections/{id}', [TenantConnectorController::class, 'update'])->whereNumber('id');
        Route::patch('/connections/{id}/status', [TenantConnectorController::class, 'setStatus'])->whereNumber('id');
        Route::delete('/connections/{id}', [TenantConnectorController::class, 'destroy'])->whereNumber('id');
        Route::post('/connections/{id}/test', [TenantConnectorController::class, 'test'])->whereNumber('id');
        // #9925 — enregistrement de la consommation de tokens d'une connexion IA.
        Route::post('/connections/{id}/usage', [TenantConnectorController::class, 'recordUsage'])->whereNumber('id');
    });

    // Lecture de la consommation — tout manager du tenant.
    Route::get('/connections/{id}/usage', [TenantConnectorController::class, 'usage'])->whereNumber('id');
});
