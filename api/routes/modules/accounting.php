<?php

declare(strict_types=1);

/**
 * Routes Module Comptabilité — #5222 contacts, #5223 documents, #5225
 * partages publics, #5229 trésorerie, #5232 settings, #5234 journal,
 * #5230 tableaux de bord, #5270 multi-devises, #5271 TVA, #5272 checkout,
 * #5273 audit trail, #5288 activation guidée (wizard), #5435 rapprochement
 * bancaire.
 *
 * RBAC (matrice comptabilité, COMPTABILITE_CONCEPTION.md §5) :
 *  - contacts/settings/TVA/convert/dashboard/activation : `comptable` (CRUD) +
 *    `principal` (lecture + paramétrage) ;
 *  - documents + journal + audit : `principal`/`comptable` ;
 *  - trésorerie + rapprochement bancaire : `principal`/`comptable`.
 * Toutes les routes exigent un employé manager du tenant courant (middleware
 * api.manager) ; l'isolation tenant est portée par le trait BelongsToCompany
 * (scope global fail-closed #3727).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingActivationController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingAuditController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCheckoutController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingCurrencyController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDashboardController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingJournalController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use App\Modules\Accounting\Interfaces\Api\V1\BankStatementController;
use App\Modules\Accounting\Interfaces\Api\V1\PublicDocumentShareController;
use Illuminate\Support\Facades\Route;

// Public: consultation + téléchargement d'un document partagé (token + throttle).
// Issue #5225 — le token de partage est la credential (pas d'auth Sanctum),
// accès RGPD limité au document partagé (pattern CabinetShare #1817).
Route::get('/accounting/documents/shared/{token}', [PublicDocumentShareController::class, 'info'])
    ->middleware('throttle:60,1');
Route::get('/accounting/documents/shared/{token}/download', [PublicDocumentShareController::class, 'download'])
    ->middleware('throttle:60,1');

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('accounting')
    ->group(function (): void {

        // ── Contacts / settings / TVA / convert / activation / dashboard ────
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

            // Conversion multi-devises (issue #5270) — calcul pur, aucun état
            // persistant : HT/TVA/TTC entre devise de document et devise de
            // référence. Taux manuel requis dès que les devises diffèrent.
            Route::post('/currency/convert', [AccountingCurrencyController::class, 'convert']);

            // Activation guidée du module (issue #5288) — wizard comptable/principal.
            Route::get('/activation', [AccountingActivationController::class, 'show']);
            Route::post('/activation', [AccountingActivationController::class, 'complete']);

            // Tableaux de bord comptables (issue #5230) — rapports manager/comptable.
            Route::get('/dashboard', [AccountingDashboardController::class, 'show']);
            Route::get('/dashboard/export', [AccountingDashboardController::class, 'export']);
        });

        // ── Documents (Phase A, #5223) + audit trail (#5273) ───────────────
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
        });

        // ── Journal des écritures (issue #5234) — débit/crédit + clôture ────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/journal', [AccountingJournalController::class, 'index']);
            Route::get('/journal/export.csv', [AccountingJournalController::class, 'export']);
            Route::post('/journal/periods/{period}/close', [AccountingJournalController::class, 'closePeriod']);
            Route::post('/documents/{document}/journal', [AccountingJournalController::class, 'postDocument'])->whereNumber('document');
        });

        // ── Trésorerie (issue #5229) + rapprochement bancaire (#5435) ───────
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/payments', [AccountingPaymentController::class, 'index']);
            Route::post('/documents/{document}/payments', [AccountingPaymentController::class, 'store'])->whereNumber('document');
            Route::post('/payments/{payment}/reconcile', [AccountingPaymentController::class, 'reconcile'])->whereNumber('payment');
            Route::post('/reminders/run', [AccountingPaymentController::class, 'runReminders']);

            // #5435 — rapprochement bancaire Phase D : import de relevé, matching
            // auto/manuel, état. Même RBAC que la trésorerie (comptable/principal).
            Route::get('/bank-statements', [BankStatementController::class, 'index']);
            Route::post('/bank-statements/import', [BankStatementController::class, 'import']);
            Route::get('/bank-statements/{statement}', [BankStatementController::class, 'show']);
            Route::post('/bank-statements/{statement}/reconcile', [BankStatementController::class, 'reconcile']);
            Route::get('/bank-statements/{statement}/status', [BankStatementController::class, 'status']);
            Route::post('/bank-statement-lines/{line}/match', [BankStatementController::class, 'match']);
        });
    });
