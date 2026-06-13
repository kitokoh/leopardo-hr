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

use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\OnboardingStepController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// ── Authenticated routes ──────────────────────────────────────────────────────
Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan'])->group(function (): void {

    // Onboarding Steps — all managers
    Route::middleware('api.manager')->group(function (): void {
        Route::get('/onboarding-setup/checklist', [OnboardingStepController::class, 'checklist']);
        Route::get('/onboarding-setup/progress', [OnboardingStepController::class, 'progress']);
        Route::patch('/onboarding-setup/{stepKey}/complete', [OnboardingStepController::class, 'complete']);
        Route::patch('/onboarding-setup/{stepKey}/skip', [OnboardingStepController::class, 'skip']);
    });

    // Feature Flags — read for all, write for principal
    Route::get('/feature-flags/matrix', [FeatureFlagController::class, 'matrix']);
    Route::get('/feature-flags/check/{featureKey}', [FeatureFlagController::class, 'check']);

    Route::middleware('api.manager:principal')->group(function (): void {
        Route::put('/feature-flags/matrix', [FeatureFlagController::class, 'updateMatrix']);
    });

    // Billing — principal only
    Route::middleware('api.manager:principal')->group(function (): void {
        Route::get('/billing/subscription', [BillingController::class, 'subscription']);
        Route::post('/billing/subscription/upgrade', [BillingController::class, 'upgrade']);
        Route::post('/billing/subscription/cancel', [BillingController::class, 'cancel']);
        Route::post('/billing/subscription/renew', [BillingController::class, 'renew']);
        Route::get('/billing/invoices', [BillingController::class, 'invoices']);
        Route::get('/billing/invoices/{id}', [BillingController::class, 'showInvoice'])->whereNumber('id');
        Route::get('/billing/invoices/{id}/pdf', [BillingController::class, 'invoicePdf'])->whereNumber('id');

        // Stripe Checkout & Portal
        Route::post('/billing/checkout', [BillingController::class, 'createCheckoutSession']);
        Route::get('/billing/portal', [BillingController::class, 'customerPortal']);
    });
});

