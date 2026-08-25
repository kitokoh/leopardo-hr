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

use App\Modules\Accounting\Interfaces\Api\V1\AccountingAuditController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCheckoutController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCurrencyController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
use App\Modules\Accounting\Interfaces\Api\V1\PublicDocumentShareController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingJournalController;
use Illuminate\Support\Facades\Route;

// Public: consultation + téléchargement d'un document partagé (token + throttle).
// Issue #5225/#5357 — le token de partage est la credential (pas d'auth Sanctum),
// accès RGPD limité au document partagé (pattern CabinetShare #1817).
// Restauré par #5512 : ces routes avaient disparu de main lors du rebuild #5498
// (documentées dans openapi.yaml + construites par DocumentShareService → 404 sinon).
Route::get('/accounting/documents/shared/{token}', [PublicDocumentShareController::class, 'info'])
    ->middleware('throttle:60,1');
Route::get('/accounting/documents/shared/{token}/download', [PublicDocumentShareController::class, 'download'])
    ->middleware('throttle:60,1');

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

            // #5273 — audit trail du module (qui/quoi/quand) — RBAC principal/comptable.
            Route::get('/audit-logs', [AccountingAuditController::class, 'index']);
        });


            // ── Rapports (issue #5271) — déclaration TVA par période.
            Route::get('/reports/vat-declaration', [AccountingReportController::class, 'vatDeclaration']);

            // ── Conversion multi-devises (issue #5270) — calcul pur, aucun
            // état persistant : HT/TVA/TTC entre devise de document et devise
            // de référence. Taux manuel requis dès que les devises diffèrent.
            Route::post('/currency/convert', [AccountingCurrencyController::class, 'convert']);

    });
/**
 * Routes API du module Comptabilité — trésorerie : paiements, rapprochement,
 * relances (issue #5229).
 *
 * RBAC : `api.manager:principal,comptable` — la trésorerie est réservée à la
 * direction et aux comptables (aucun accès RH/marketing).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;
use App\Modules\Accounting\Interfaces\Api\V1\BankStatementController;

Route::middleware(['auth:sanctum', 'token.refresh', 'tenant', 'api.manager:principal,comptable'])->group(function (): void {
    Route::get('accounting/payments', [AccountingPaymentController::class, 'index']);
    Route::post('accounting/documents/{document}/payments', [AccountingPaymentController::class, 'store']);
    Route::post('accounting/payments/{payment}/reconcile', [AccountingPaymentController::class, 'reconcile']);
    Route::post('accounting/reminders/run', [AccountingPaymentController::class, 'runReminders']);
    // #5435 — rapprochement bancaire Phase D : import de relevé, matching
    // auto/manuel, état. Même RBAC que la trésorerie (comptable/principal).
    Route::get('accounting/bank-statements', [BankStatementController::class, 'index']);
    Route::post('accounting/bank-statements/import', [BankStatementController::class, 'import']);
    Route::get('accounting/bank-statements/{statement}', [BankStatementController::class, 'show']);
    Route::post('accounting/bank-statements/{statement}/reconcile', [BankStatementController::class, 'reconcile']);
    Route::get('accounting/bank-statements/{statement}/status', [BankStatementController::class, 'status']);
    Route::post('accounting/bank-statement-lines/{line}/match', [BankStatementController::class, 'match']);
});

// Journal des écritures débit/crédit + clôture de période (issue #5234).
// Restauré par #5512 : le rebuild #5498 avait fait disparaître ces routes de
// main (contrôleur AccountingJournalController orphelin, opérations toujours
// documentées dans openapi.yaml → tout client suivant la spec recevait 404).
// RBAC : principal/comptable (matrice comptabilité #5226).
Route::middleware(['auth:sanctum', 'token.refresh', 'tenant', 'api.manager:principal,comptable'])->group(function (): void {
    Route::get('accounting/journal', [AccountingJournalController::class, 'index']);
    Route::get('accounting/journal/export.csv', [AccountingJournalController::class, 'export']);
    Route::post('accounting/journal/periods/{period}/close', [AccountingJournalController::class, 'closePeriod']);
    Route::post('accounting/documents/{document}/journal', [AccountingJournalController::class, 'postDocument']);
});
