<?php

declare(strict_types=1);

/**
 * Routes Module Comptabilité — #5222 contacts, #5223 documents, #5229
 * trésorerie (paiements/rapprochement/relances), #5232 settings, #5271 TVA,
 * #5230 tableaux de bord.
 *
 * RBAC (matrice comptabilité, COMPTABILITE_CONCEPTION.md §5) :
 *  - contacts/settings/TVA/dashboard : `comptable` (CRUD) + `principal`
 *    (lecture + paramétrage) ;
 *  - documents : `principal`/`comptable` ;
 *  - trésorerie : `principal`/`comptable` (paiements, rapprochement, relances).
 * Toutes les routes exigent un employé manager du tenant courant (middleware
 * api.manager) ; l'isolation tenant est portée par le trait BelongsToCompany
 * (scope global fail-closed #3727).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDashboardController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use Illuminate\Support\Facades\Route;

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
        });

        // ── Trésorerie (issue #5229) — paiements, rapprochement, relances ───
        Route::middleware('api.manager:principal,comptable')->group(function (): void {
            Route::get('/payments', [AccountingPaymentController::class, 'index']);
            Route::post('/documents/{document}/payments', [AccountingPaymentController::class, 'store'])->whereNumber('document');
            Route::post('/payments/{payment}/reconcile', [AccountingPaymentController::class, 'reconcile'])->whereNumber('payment');
            Route::post('/reminders/run', [AccountingPaymentController::class, 'runReminders']);
        });
    });
