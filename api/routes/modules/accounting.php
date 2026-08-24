<?php

declare(strict_types=1);

/**
 * Routes API du module Comptabilité — contacts, documents, trésorerie,
 * ordres de virement salarial et journal des écritures (issues #5222, #5223,
 * #5229, #5239, #5271).
 *
 * RBAC (matrice comptabilité, docs/security/RBAC_ACCOUNTING_MATRIX.md) :
 *   - contacts client/fournisseur : `comptable` (CRUD complet), `principal` (lecture + paramétrage) ;
 *   - documents (Phase A, #5223) : `principal`/`comptable` ;
 *   - trésorerie (paiements, rapprochement, relances, #5229) : `principal`/`comptable` ;
 *   - ordres de virement salarial (#5239) : lecture `principal`/`comptable`, écriture `comptable` ;
 *   - journal des écritures salariales (#5239, Partie 1) : lecture `principal`/`comptable`
 *     — la génération est déclenchée à la validation du run de paie (événement
 *     `PayrollRunValidated`) et rattrapable par la commande
 *     `accounting:generate-payroll-entries`.
 * Toutes les routes exigent un employé manager du tenant courant (middleware
 * api.manager) ; l'isolation tenant est portée par le trait BelongsToCompany
 * (scope global fail-closed #3727).
 */

use App\Modules\Accounting\Interfaces\Api\V1\AccountingContactController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingDocumentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingJournalEntryController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingPaymentOrderController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingReportController;
use App\Modules\Accounting\Interfaces\Api\V1\AccountingSettingsController;
use Illuminate\Support\Facades\Route;

// ── Contacts, paramétrage, rapports (RBAC comptable + principal) ──────────
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('accounting')
    ->group(function (): void {
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

// ── Trésorerie — paiements + rapprochement + relances (issue #5229) ────────
Route::middleware(['auth:sanctum', 'token.refresh', 'tenant', 'api.manager:principal,comptable'])->group(function (): void {
    Route::get('accounting/payments', [AccountingPaymentController::class, 'index']);
    Route::post('accounting/documents/{document}/payments', [AccountingPaymentController::class, 'store']);
    Route::post('accounting/payments/{payment}/reconcile', [AccountingPaymentController::class, 'reconcile']);
    Route::post('accounting/reminders/run', [AccountingPaymentController::class, 'runReminders']);
});

// ── Ordres de virement salarial (issue #5239, Phase C) — RBAC comptable/principal ──
// NB : préfixe déclaré via ->prefix('accounting') (et non chemin absolu) pour
// rester lisible par la garde OpenAPI #1473 (parser statique — le préfixe v1
// hérité de api.php est consommé par le groupe précédent).
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal,comptable'])->prefix('accounting')->group(function (): void {
    Route::get('/payment-orders', [AccountingPaymentOrderController::class, 'index']);
    Route::get('/payment-orders/{order}', [AccountingPaymentOrderController::class, 'show'])->whereNumber('order');
});

// Actions d'écriture — comptable uniquement (création, préparation, exécution).
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:comptable'])->prefix('accounting')->group(function (): void {
    Route::post('/payment-orders', [AccountingPaymentOrderController::class, 'store']);
    Route::post('/payment-orders/{order}/prepare', [AccountingPaymentOrderController::class, 'prepare'])->whereNumber('order');
    Route::post('/payment-orders/{order}/execute', [AccountingPaymentOrderController::class, 'execute'])->whereNumber('order');
});

// ── Journal des écritures salariales (issue #5239, Phase C — Partie 1) ─────
// Lecture seule RBAC comptable/principal — la génération est déclenchée à la
// validation du run (événement PayrollRunValidated, dispatch additif dans
// PayrollRunController::validateRun) et rattrapable par la commande
// `accounting:generate-payroll-entries --run={id}`.
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal,comptable'])->prefix('accounting')->group(function (): void {
    Route::get('/journal-entries', [AccountingJournalEntryController::class, 'index']);
    Route::get('/journal-entries/{entry}', [AccountingJournalEntryController::class, 'show'])->whereNumber('entry');
});
