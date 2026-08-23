<?php

/**
 * Routes Module Comptabilité — issue #5222 (CRUD contacts).
 *
 * RBAC (matrice comptabilité) : contacts client/fournisseur —
 * `comptable` (CRUD complet), `principal` (lecture + paramétrage).
 * Toutes les routes exigent un employé manager du tenant courant
 * (middleware api.manager) ; l'isolation tenant est portée par le trait
 * BelongsToCompany (scope global fail-closed #3727).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('accounting')
    ->group(function (): void {

        // ── Contacts client/fournisseur (RBAC comptable + principal) ────────
        Route::middleware('api.manager:comptable,principal')->group(function (): void {
            Route::get('/contacts', [AccountingContactController::class, 'index']);
            Route::post('/contacts', [AccountingContactController::class, 'store']);
            Route::get('/contacts/{contact}', [AccountingContactController::class, 'show'])->whereNumber('contact');
            Route::put('/contacts/{contact}', [AccountingContactController::class, 'update'])->whereNumber('contact');
            Route::delete('/contacts/{contact}', [AccountingContactController::class, 'destroy'])->whereNumber('contact');

            // ── Paramétrage comptable (issue #5232) — une ligne par entreprise
            // GET  : settings persistés ou défauts pays (CountryDefaults) ;
            // PUT  : upsert avec validation (devise, TVA, séries, mentions).
            Route::get('/settings', [AccountingSettingsController::class, 'show']);
            Route::put('/settings', [AccountingSettingsController::class, 'update']);

            // ── Rapports (issue #5271) — déclaration TVA par période.
            Route::get('/reports/vat-declaration', [AccountingReportController::class, 'vatDeclaration']);
        });
    });
 * Routes module Comptabilité (issue #5225).
 *
 * Endpoints PUBLICS du portail client : le token de partage est la credential
 * (pas d'auth Sanctum) — accès RGPD limité au document partagé, pattern
 * CabinetShare (#1817). Les endpoints authentifiés arrivent avec #5226.
 */

use App\Modules\Accounting\Interfaces\Api\V1\PublicDocumentShareController;
use Illuminate\Support\Facades\Route;

// Public: consultation + téléchargement d'un document partagé (token + throttle).
Route::get('/accounting/documents/shared/{token}', [PublicDocumentShareController::class, 'info'])
    ->middleware('throttle:60,1');
Route::get('/accounting/documents/shared/{token}/download', [PublicDocumentShareController::class, 'download'])
    ->middleware('throttle:60,1');
