<?php

/**
 * Routes SSO — SAML 2.0 / OpenID Connect stub.
 * Plan 15 item K2.
 */

use App\Http\Controllers\Api\V1\SSO\SSOController;
use Illuminate\Support\Facades\Route;

// Public: supported providers list
Route::get('/sso/providers', [SSOController::class, 'providers']);

// Public: SSO callbacks (no auth required — these receive IdP responses)
Route::post('/sso/saml/{companyId}/callback', [SSOController::class, 'samlCallback']);
Route::get('/sso/oidc/{companyId}/callback', [SSOController::class, 'oidcCallback']);

// Authenticated: SSO management (manager principal only)
Route::middleware(['throttle:api', 'auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('/sso/status', [SSOController::class, 'status']);
    Route::post('/sso/configure', [SSOController::class, 'configure']);
    Route::delete('/sso/disable', [SSOController::class, 'disable']);
});
