<?php

declare(strict_types=1);

/**
 * Routes Module Comptabilité — issues #5222 (contacts), #5223 (documents),
 * #5229 (trésorerie), #5234 (journal), #5271 (TVA).
 *
 * RBAC (matrice comptabilité) : contacts client/fournisseur —
 * `comptable` (CRUD complet), `principal` (lecture + paramétrage) ;
 * documents, trésorerie et journal (Phase A/B/C) — `principal`/`comptable`.
 * Toutes les routes exigent un employé manager du tenant courant
 * (middleware api.manager) ; l'isolation tenant est portée par le trait
 * BelongsToCompany (scope global fail-closed #3727).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingJournalController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
use App\Modules\Accounting\Interfaces\Api\V1\PublicDocumentShareController;
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
        });
    });

// ── Portail client (issue #5225) — endpoints PUBLICS, le token est la credential.
// Accès RGPD limité au document partagé, pattern CabinetShare (#1817).
Route::get('/accounting/documents/shared/{token}', [PublicDocumentShareController::class, 'info'])
    ->middleware('throttle:60,1');
Route::get('/accounting/documents/shared/{token}/download', [PublicDocumentShareController::class, 'download'])
    ->middleware('throttle:60,1');

// ── Trésorerie : paiements, rapprochement, relances (issue #5229) ──────────
// RBAC : `api.manager:principal,comptable` — réservé direction + comptables.
Route::middleware(['auth:sanctum', 'token.refresh', 'tenant', 'api.manager:principal,comptable'])->group(function (): void {
    Route::get('accounting/payments', [AccountingPaymentController::class, 'index']);
    Route::post('accounting/documents/{document}/payments', [AccountingPaymentController::class, 'store']);
    Route::post('accounting/payments/{payment}/reconcile', [AccountingPaymentController::class, 'reconcile']);
    Route::post('accounting/reminders/run', [AccountingPaymentController::class, 'runReminders']);
});

// ── Journal des écritures (issue #5234) — RBAC principal/comptable ─────────
Route::middleware(['auth:sanctum', 'token.refresh', 'tenant', 'api.manager:principal,comptable'])->group(function (): void {
    Route::get('accounting/journal', [AccountingJournalController::class, 'index']);
    Route::get('accounting/journal/export.csv', [AccountingJournalController::class, 'export']);
    Route::post('accounting/journal/periods/{period}/close', [AccountingJournalController::class, 'closePeriod']);
    Route::post('accounting/documents/{document}/journal', [AccountingJournalController::class, 'postDocument']);
});
