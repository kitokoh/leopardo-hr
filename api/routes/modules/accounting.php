<?php

declare(strict_types=1);

/**
 * Routes Module Comptabilité — #5222 contacts, #5223 documents, #5225 portail
 * client, #5229 trésorerie (paiements/rapprochement/relances), #5232 settings,
 * #5234 journal, #5230 tableaux de bord, #5270 multi-devises, #5271 TVA.
 *
 * RBAC (matrice comptabilité, COMPTABILITE_CONCEPTION.md §5) :
 *  - contacts/settings/TVA/dashboard : `comptable` (CRUD) + `principal`
 *    (lecture + paramétrage) ;
 *  - documents, trésorerie, journal, audit, devises : `principal`/`comptable` ;
 *  - portail client : endpoints PUBLICS — le token de partage est la
 *    credential (accès RGPD limité au document partagé, pattern
 *    CabinetShare #1817).
 * Toutes les routes authentifiées exigent un employé manager du tenant courant
 * (middleware api.manager) ; l'isolation tenant est portée par le trait
 * BelongsToCompany (scope global fail-closed #3727).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingActivationController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingAuditController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingChartController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCurrencyController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDashboardController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingJournalController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingFecController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingFiscalYearController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingLedgerController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingLetteringController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingStatementController;
use App\Modules\Accounting\Interfaces\Api\V1\PublicDocumentShareController;
use Illuminate\Support\Facades\Route;

// ── Portail client (issue #5225) — endpoints PUBLICS, le token est la credential.
Route::get('/accounting/documents/shared/{token}', [PublicDocumentShareController::class, 'info'])
    ->middleware('throttle:60,1');
Route::get('/accounting/documents/shared/{token}/download', [PublicDocumentShareController::class, 'download'])
    ->middleware('throttle:60,1');

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('accounting')
    ->group(function (): void {

        // ── Contacts / settings / TVA / dashboard (RBAC comptable + principal)
        Route::middleware('api.manager:comptable,principal')->group(function (): void {
            Route::get('/contacts', [AccountingContactController::class, 'index']);
            Route::post('/contacts', [AccountingContactController::class, 'store']);
            Route::get('/contacts/{contact}', [AccountingContactController::class, 'show'])->whereNumber('contact');
            Route::put('/contacts/{contact}', [AccountingContactController::class, 'update'])->whereNumber('contact');
            Route::delete('/contacts/{contact}', [AccountingContactController::class, 'destroy'])->whereNumber('contact');

            // Paramétrage comptable (issue #5232) — une ligne par entreprise.
            Route::get('/settings', [AccountingSettingsController::class, 'show']);
            Route::put('/settings', [AccountingSettingsController::class, 'update']);

            // Déclaration TVA par période (issue #5271).
            Route::get('/reports/vat-declaration', [AccountingReportController::class, 'vatDeclaration']);

            // Tableaux de bord comptables (issue #5230) — rapports manager/comptable.
            Route::get('/dashboard', [AccountingDashboardController::class, 'show']);
            Route::get('/dashboard/export', [AccountingDashboardController::class, 'export']);

            // Wizard d'activation Comptabilité (issue #5288) — check-list + complétion idempotente.
            Route::get('/activation', [AccountingActivationController::class, 'show']);
            Route::post('/activation', [AccountingActivationController::class, 'complete']);

            // Plan comptable (issue #5422) — CRUD comptes, provisionné par
            // défaut à la création d'entreprise (ChartOfAccountsService).
            Route::get('/chart', [AccountingChartController::class, 'index']);
            Route::post('/chart', [AccountingChartController::class, 'store']);
            Route::get('/chart/{code}', [AccountingChartController::class, 'show'])->where('code', '[0-9]+');
            Route::put('/chart/{code}', [AccountingChartController::class, 'update'])->where('code', '[0-9]+');
            Route::delete('/chart/{code}', [AccountingChartController::class, 'destroy'])->where('code', '[0-9]+');
        });

        // ── Documents (Phase A, #5223) — RBAC principal/comptable ───────────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/documents', [AccountingDocumentController::class, 'index']);
            Route::post('/documents', [AccountingDocumentController::class, 'store']);
            Route::get('/documents/next-number', [AccountingDocumentController::class, 'nextNumber']);
            Route::get('/documents/{document}', [AccountingDocumentController::class, 'show'])->whereNumber('document');
            Route::post('/documents/{document}/send', [AccountingDocumentController::class, 'send'])->whereNumber('document');
            Route::post('/documents/{document}/cancel', [AccountingDocumentController::class, 'cancel'])->whereNumber('document');
            Route::post('/documents/{document}/credit-note', [AccountingDocumentController::class, 'creditNote'])->whereNumber('document');

            // #5273 — audit trail scope module (qui/quoi/quand).
            Route::get('/audit-logs', [AccountingAuditController::class, 'index']);
        });

        // ── Trésorerie / journal / devises (RBAC principal/comptable) ───────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/payments', [AccountingPaymentController::class, 'index']);
            Route::post('/documents/{document}/payments', [AccountingPaymentController::class, 'store'])->whereNumber('document');
            Route::post('/payments/{payment}/reconcile', [AccountingPaymentController::class, 'reconcile'])->whereNumber('payment');
            Route::post('/reminders/run', [AccountingPaymentController::class, 'runReminders']);

            // ── Journal des écritures (issue #5234) — RBAC principal/comptable
            Route::get('/journal', [AccountingJournalController::class, 'index']);
            Route::get('/journal/export.csv', [AccountingJournalController::class, 'export']);
            Route::post('/journal/periods/{period}/close', [AccountingJournalController::class, 'closePeriod']);
            Route::post('/documents/{document}/journal', [AccountingJournalController::class, 'postDocument']);

            // ── Multi-devises (issue #5270) — conversion de devises.
            Route::post('/currency/convert', [AccountingCurrencyController::class, 'convert']);

            // ── Grand livre + balance de vérification (issue #5422) ─────────
            Route::get('/ledger', [AccountingLedgerController::class, 'index']);
            Route::get('/balance', [AccountingLedgerController::class, 'balance']);

            // ── États financiers (issue #5422) — bilan + compte de résultat.
            Route::get('/statements/balance-sheet', [AccountingStatementController::class, 'balanceSheet']);
            Route::get('/statements/income-statement', [AccountingStatementController::class, 'incomeStatement']);

            // ── Export FEC (issue #5422) — fichier des écritures comptables.
            Route::get('/journal/export-fec', [AccountingFecController::class, 'export']);

            // ── Exercices comptables (issue #5422) — ouverture + clôture.
            Route::get('/fiscal-years', [AccountingFiscalYearController::class, 'index']);
            Route::post('/fiscal-years', [AccountingFiscalYearController::class, 'store']);
            Route::post('/fiscal-years/{year}/close', [AccountingFiscalYearController::class, 'close'])->whereNumber('year');

            // ── Lettrage des comptes de tiers (issue #5422).
            Route::post('/journal/lettering', [AccountingLetteringController::class, 'store']);
            Route::delete('/journal/lettering/{letter}', [AccountingLetteringController::class, 'destroy']);
        });
    });
