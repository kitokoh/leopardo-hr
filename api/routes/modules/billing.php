<?php

/**
 * Routes Billing, Onboarding & Feature Flags — Sprint 13-14.
 *
 * RBAC:
 *   - Billing: principal only (subscription, invoices, upgrade/cancel)
 *   - Onboarding setup: all managers (checklist)
 *   - Feature flags read: all authenticated
 *   - Feature flags write: principal only
 */

use App\Modules\Billing\Interfaces\Api\V1\BillingController;
use App\Modules\Billing\Interfaces\Api\V1\FeatureFlagController;
use App\Modules\Onboarding\Interfaces\Api\V1\Controllers\OnboardingStepController;
use Illuminate\Support\Facades\Route;

// ── Authenticated routes ──────────────────────────────────────────────────────
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {

    // Onboarding Steps — tous les utilisateurs authentifiés du tenant
    // (l'app Employee complète son onboarding : T118 — plus de 403
    // api.manager pour un employé non-manager).
    Route::get('/onboarding-setup/checklist', [OnboardingStepController::class, 'checklist']);
    Route::get('/onboarding-setup/progress', [OnboardingStepController::class, 'progress']);
    // #3430 : écritures d'état company-level réservées aux managers (api.manager) —
    // un employé simple ne peut plus falsifier le progrès d'onboarding de l'entreprise.
    Route::post('/onboarding-setup/{stepKey}/complete', [OnboardingStepController::class, 'complete'])
        ->middleware('api.manager');
    // #4930 : action métier → POST ; PATCH déprécié conservé (rétrocompatibilité).
    Route::patch('/onboarding-setup/{stepKey}/complete', [OnboardingStepController::class, 'complete'])
        ->middleware('api.manager');
    Route::post('/onboarding-setup/{stepKey}/skip', [OnboardingStepController::class, 'skip'])
        ->middleware('api.manager');
    Route::patch('/onboarding-setup/{stepKey}/skip', [OnboardingStepController::class, 'skip'])
        ->middleware('api.manager');

    // Alias expected by mobile/web clients: list onboarding steps.
    Route::get('/onboarding/steps', [OnboardingStepController::class, 'checklist']);

    // Feature Flags — read for all (la mise à jour de la matrice est réservée à
    // l'administration plateforme ; l'endpoint PUT #3892 n'a jamais été implémenté
    // et renvoyait 403 en dur — retiré du contrat).
    Route::get('/feature-flags/matrix', [FeatureFlagController::class, 'matrix']);
    Route::get('/feature-flags/check/{featureKey}', [FeatureFlagController::class, 'check']);

    // Billing — principal only
    Route::middleware('api.manager:principal')->group(function (): void {
        Route::get('/billing/subscription', [BillingController::class, 'subscription']);
        Route::post('/billing/subscription/upgrade', [BillingController::class, 'upgrade']);
        Route::post('/billing/subscription/cancel', [BillingController::class, 'cancel']);
        Route::post('/billing/subscription/renew', [BillingController::class, 'renew']);
        Route::get('/billing/invoices', [BillingController::class, 'invoices']);
        Route::get('/billing/invoices/{id}', [BillingController::class, 'showInvoice'])->whereNumber('id');
        // #4931 : GET idempotent accepté — génération PDF pure (aucune écriture).
        Route::get('/billing/invoices/{id}/pdf', [BillingController::class, 'invoicePdf'])->whereNumber('id');

        // Stripe Checkout & Portal
        Route::post('/billing/checkout', [BillingController::class, 'createCheckoutSession']);
        // #4931 : customerPortal CRÉE une session Stripe (effet de bord) →
        // POST, jamais GET. La réponse reste la même (URL du portal).
        Route::post('/billing/portal', [BillingController::class, 'customerPortal']);
    });
});
