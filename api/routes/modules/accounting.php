<?php

declare(strict_types=1);

/**
 * Routes Module Comptabilité — issue #5222 (CRUD contacts) et #5223 (documents).
 *
 * RBAC (matrice comptabilité) : contacts client/fournisseur —
 * `comptable` (CRUD complet), `principal` (lecture + paramétrage) ;
 * documents (Phase A, #5223) — `principal`/`comptable`.
 * Toutes les routes exigent un employé manager du tenant courant
 * (middleware api.manager) ; l'isolation tenant est portée par le trait
 * BelongsToCompany (scope global fail-closed #3727).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingCheckoutController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCurrencyController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
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

            // ── Conversion multi-devises (issue #5270) — calcul pur, aucun
            // état persistant : HT/TVA/TTC entre devise de document et devise
            // de référence. Taux manuel requis dès que les devises diffèrent.
            Route::post('/currency/convert', [AccountingCurrencyController::class, 'convert']);
        });

        // ── Documents (Phase A, #5223) — RBAC principal/comptable ───────────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/documents', [AccountingDocumentController::class, 'index']);
            Route::post('/documents', [AccountingDocumentController::class, 'store']);
            Route::get('/documents/next-number', [AccountingDocumentController::class, 'nextNumber']);
            Route::get('/documents/{document}', [AccountingDocumentController::class, 'show'])->whereNumber('document');
            Route::post('/documents/{document}/send', [AccountingDocumentController::class, 'send'])->whereNumber('document');
            Route::post('/documents/{document}/payments', [AccountingDocumentController::class, 'payments'])->whereNumber('document');
            Route::post('/documents/{document}/cancel', [AccountingDocumentController::class, 'cancel'])->whereNumber('document');
            Route::post('/documents/{document}/credit-note', [AccountingDocumentController::class, 'creditNote'])->whereNumber('document');
            // #5272 — paiement en ligne : initiation d'une session de checkout
            // (Chargily DZ / Stripe), routée par pays de l'entreprise (ADR-0017).
            Route::post('/documents/{document}/checkout', [AccountingCheckoutController::class, 'store'])->whereNumber('document');
        });
    });
/**
 * Routes API du module Comptabilité — trésorerie : paiements, rapprochement,
 * relances (issue #5229).
 *
 * RBAC : `api.manager:principal,comptable` — la trésorerie est réservée à la
 * direction et aux comptables (aucun accès RH/marketing).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;

Route::middleware(['auth:sanctum', 'token.refresh', 'tenant', 'api.manager:principal,comptable'])->group(function (): void {
    Route::get('accounting/payments', [AccountingPaymentController::class, 'index']);
    Route::post('accounting/documents/{document}/payments', [AccountingPaymentController::class, 'store']);
    Route::post('accounting/payments/{payment}/reconcile', [AccountingPaymentController::class, 'reconcile']);
    Route::post('accounting/reminders/run', [AccountingPaymentController::class, 'runReminders']);
});
/**
 * Routes API du module Comptabilité — journal des écritures (issue #5234).
 * RBAC : `api.manager:principal,comptable` — journal réservé à la direction et aux comptables.
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingJournalController;

Route::middleware(['auth:sanctum', 'token.refresh', 'tenant', 'api.manager:principal,comptable'])
    ->prefix('accounting')
    ->group(function (): void {
        Route::get('/journal', [AccountingJournalController::class, 'index']);
        Route::get('/journal/export.csv', [AccountingJournalController::class, 'export']);
        Route::post('/journal/periods/{period}/close', [AccountingJournalController::class, 'closePeriod']);
        Route::post('/documents/{document}/journal', [AccountingJournalController::class, 'postDocument']);
    });
