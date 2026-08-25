<?php

declare(strict_types=1);

/**
 * Routes Module Comptabilité — consolidation des blocs historiques (#5222,
 * #5223, #5229, #5232, #5270, #5271, #5272, #5273, #5435).
 *
 * RBAC (matrice comptabilité) : contacts/settings/currency — comptable +
 * principal ; documents + trésorerie — principal/comptable. Toutes les routes
 * exigent un employé manager du tenant courant (middleware api.manager) ;
 * l'isolation tenant est portée par le trait BelongsToCompany (fail-closed
 * #3727). Le fichier a été reconstruit le 2026-08-25 : les merges de la
 * consolidation #5422 avaient concaténé 3 versions du fichier (imports
 * dupliqués → Fatal, routes trésorerie perdues).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingAuditController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCheckoutController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCurrencyController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use App\Modules\Accounting\Interfaces\Api\V1\PublicDocumentShareController;
use Illuminate\Support\Facades\Route;

// ── Public : consultation + téléchargement d'un document partagé (token) ──
// Issue #5225 — le token de partage est la credential (pas d'auth Sanctum),
// accès RGPD limité au document partagé (pattern CabinetShare #1817).
Route::get('/accounting/documents/shared/{token}', [PublicDocumentShareController::class, 'info'])
    ->middleware('throttle:60,1');
Route::get('/accounting/documents/shared/{token}/download', [PublicDocumentShareController::class, 'download'])
    ->middleware('throttle:60,1');

// ── Surface authentifiée du module ─────────────────────────────────────────
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('accounting')
    ->group(function (): void {

        // ── Contacts client/fournisseur + paramétrage + conversion (comptable/principal) ──
        Route::middleware('api.manager:comptable,principal')->group(function (): void {
            Route::get('/contacts', [AccountingContactController::class, 'index']);
            Route::post('/contacts', [AccountingContactController::class, 'store']);
            Route::get('/contacts/{contact}', [AccountingContactController::class, 'show'])->whereNumber('contact');
            Route::put('/contacts/{contact}', [AccountingContactController::class, 'update'])->whereNumber('contact');
            Route::delete('/contacts/{contact}', [AccountingContactController::class, 'destroy'])->whereNumber('contact');

            // Paramétrage comptable (issue #5232) — une ligne par entreprise.
            Route::get('/settings', [AccountingSettingsController::class, 'show']);
            Route::put('/settings', [AccountingSettingsController::class, 'update']);

            // Conversion multi-devises (issue #5270) — calcul pur, aucun enregistrement.
            Route::post('/currency/convert', [AccountingCurrencyController::class, 'convert']);


            // Rapports (issue #5271) — déclaration TVA par période.
            Route::get('/reports/vat-declaration', [AccountingReportController::class, 'vatDeclaration']);
        });

        // ── Documents + audit (principal/comptable, issue #5223/#5273) ───────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/documents', [AccountingDocumentController::class, 'index']);
            Route::post('/documents', [AccountingDocumentController::class, 'store']);
            Route::get('/documents/next-number', [AccountingDocumentController::class, 'nextNumber']);
            Route::get('/documents/{document}', [AccountingDocumentController::class, 'show'])->whereNumber('document');
            Route::post('/documents/{document}/send', [AccountingDocumentController::class, 'send'])->whereNumber('document');
            Route::post('/documents/{document}/payments', [AccountingDocumentController::class, 'payments'])->whereNumber('document');
            Route::post('/documents/{document}/cancel', [AccountingDocumentController::class, 'cancel'])->whereNumber('document');
            Route::post('/documents/{document}/credit-note', [AccountingDocumentController::class, 'creditNote'])->whereNumber('document');
            // Paiement en ligne (issue #5272) — session de checkout (Chargily/Stripe).
            Route::post('/documents/{document}/checkout', [AccountingCheckoutController::class, 'store'])->whereNumber('document');

            // Audit trail scope module (issue #5273) — qui/quoi/quand.
            Route::get('/audit-logs', [AccountingAuditController::class, 'index']);
        });

        // ── Trésorerie (issue #5229) — paiements, rapprochement, relances ────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/payments', [AccountingPaymentController::class, 'index']);
            Route::post('/payments/{payment}/reconcile', [AccountingPaymentController::class, 'reconcile'])->whereNumber('payment');
            Route::post('/reminders/run', [AccountingPaymentController::class, 'runReminders']);
        });
    });
