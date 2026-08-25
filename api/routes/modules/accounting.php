<?php

declare(strict_types=1);

/**
 * Routes Module Comptabilité — issue #5222 (CRUD contacts), #5223 (documents),
 * #5271 (TVA), #5270 (multi-devises), #5272 (paiement en ligne), #5273 (audit),
 * #5422 (profondeur : plan comptable, grand livre, balance, états financiers,
 * FEC, exercices, lettrage) et #5229/#5435 (trésorerie + rapprochement bancaire).
 *
 * RBAC (matrice comptabilité) : contacts client/fournisseur —
 * `comptable` (CRUD complet), `principal` (lecture + paramétrage) ;
 * documents, trésorerie, profondeur — `principal`/`comptable`.
 * Toutes les routes exigent un employé manager du tenant courant
 * (middleware api.manager) ; l'isolation tenant est portée par le trait
 * BelongsToCompany (scope global fail-closed #3727).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingAuditController;
use App\Modules\Accounting\Interfaces\Api\V1\BankStatementController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingActivationController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDashboardController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingJournalController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingChartController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCheckoutController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCurrencyController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingFecController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingFiscalYearController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingLedgerController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingLetteringController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingStatementController;
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
        });

        // ── Documents, TVA, conversion, audit (RBAC principal/comptable) ────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/documents', [AccountingDocumentController::class, 'index']);
            Route::post('/documents', [AccountingDocumentController::class, 'store']);
            Route::get('/documents/next-number', [AccountingDocumentController::class, 'nextNumber']);
            Route::get('/documents/{document}', [AccountingDocumentController::class, 'show'])->whereNumber('document');
            Route::post('/documents/{document}/send', [AccountingDocumentController::class, 'send'])->whereNumber('document');
            Route::post('/documents/{document}/cancel', [AccountingDocumentController::class, 'cancel'])->whereNumber('document');
            Route::post('/documents/{document}/credit-note', [AccountingDocumentController::class, 'creditNote'])->whereNumber('document');
            // #5272 — paiement en ligne : initiation d'une session de checkout
            // (Chargily DZ / Stripe), routée par pays de l'entreprise (ADR-0017).
            Route::post('/documents/{document}/checkout', [AccountingCheckoutController::class, 'store'])->whereNumber('document');

            // #5273 — audit trail du module (qui/quoi/quand) — RBAC principal/comptable.
            Route::get('/audit-logs', [AccountingAuditController::class, 'index']);

            // ── Rapports (issue #5271) — déclaration TVA par période.
            Route::get('/reports/vat-declaration', [AccountingReportController::class, 'vatDeclaration']);

            // ── Conversion multi-devises (issue #5270) — calcul pur, aucun
            // état persistant : HT/TVA/TTC entre devise de document et devise
            // de référence. Taux manuel requis dès que les devises diffèrent.
            Route::post('/currency/convert', [AccountingCurrencyController::class, 'convert']);
        });

        // ── Profondeur comptable (issue #5422) — RBAC principal/comptable ────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            // Plan comptable paramétrable par entreprise (classes PCG/SCF 1-8,
            // provisionné à la création d'entreprise ; comptes système non
            // supprimables).
            Route::get('/chart', [AccountingChartController::class, 'index']);
            Route::post('/chart', [AccountingChartController::class, 'store']);
            Route::get('/chart/{code}', [AccountingChartController::class, 'show']);
            Route::put('/chart/{code}', [AccountingChartController::class, 'update']);
            Route::delete('/chart/{code}', [AccountingChartController::class, 'destroy']);

            // Grand livre + balance de vérification (running balance continu).
            Route::get('/ledger', [AccountingLedgerController::class, 'index']);
            Route::get('/balance', [AccountingLedgerController::class, 'balance']);

            // Bilan + compte de résultat (sections par classe PCG).
            Route::get('/statements/balance-sheet', [AccountingStatementController::class, 'balanceSheet']);
            Route::get('/statements/income-statement', [AccountingStatementController::class, 'incomeStatement']);

            // Export FEC DGFiP (13 colonnes, numérotation par pièce).
            Route::get('/journal/export-fec', [AccountingFecController::class, 'export']);

            // Exercices comptables : ouverture + clôture (report à nouveau 12/891).
            Route::get('/fiscal-years', [AccountingFiscalYearController::class, 'index']);
            Route::post('/fiscal-years', [AccountingFiscalYearController::class, 'store']);
            Route::post('/fiscal-years/{year}/close', [AccountingFiscalYearController::class, 'close'])->whereNumber('year');

            // Journal des écritures (issue #5234) — période, export CSV expert,
            // clôture de période (fige le journal) et re-posting d'un document.
            Route::get('/journal', [AccountingJournalController::class, 'index']);
            Route::get('/journal/export.csv', [AccountingJournalController::class, 'export']);
            Route::post('/journal/periods/{period}/close', [AccountingJournalController::class, 'closePeriod']);
            Route::post('/documents/{document}/journal', [AccountingJournalController::class, 'postDocument'])->whereNumber('document');

            // Tableaux de bord comptables (issue #5395) — synthèse + export CSV impayés.
            Route::get('/dashboard', [AccountingDashboardController::class, 'show']);
            Route::get('/dashboard/export', [AccountingDashboardController::class, 'export']);

            // Wizard d'activation Comptabilité (issue #5288).
            Route::get('/activation', [AccountingActivationController::class, 'show']);
            Route::post('/activation/complete', [AccountingActivationController::class, 'complete']);

            // Lettrage des comptes de tiers (équilibre Σ débits = Σ crédits).
            Route::post('/journal/lettering', [AccountingLetteringController::class, 'store']);
            Route::delete('/journal/lettering/{letter}', [AccountingLetteringController::class, 'destroy']);
        });
    });

/**
 * Routes API du module Comptabilité — trésorerie : paiements, rapprochement
 * bancaire, relances (issues #5229, #5435).
 *
 * RBAC : `api.manager:principal,comptable` — la trésorerie est réservée à la
 * direction et aux comptables (aucun accès RH/marketing).
 */

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
