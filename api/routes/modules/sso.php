<?php

/**
 * Routes SSO — SAML 2.0 / OpenID Connect stub.
 * Plan 15 item K2.
 */

use App\Core\Auth\Interfaces\Api\V1\SSOController;
use Illuminate\Support\Facades\Route;

// Public: supported providers list
Route::get('/sso/providers', [SSOController::class, 'providers']);

// Public: SSO callbacks (no auth required — these receive IdP responses).
// #3497 : throttled — le callback SAML décode/parse du XML non authentifié et
// les flux OIDC font du fetch config-driven (SSRF #3318) ; 10/min par IP borne
// le DoS/brute-force sans casser les retries IdP légitimes.
Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('/sso/saml/{companyId}/callback', [SSOController::class, 'samlCallback']);
    Route::get('/sso/oidc/{companyId}/authorize', [SSOController::class, 'oidcAuthorize'])->whereUuid('companyId');
    Route::get('/sso/oidc/{companyId}/callback', [SSOController::class, 'oidcCallback'])->whereUuid('companyId');
});

// Authenticated: SSO management (manager principal only)
// #2635 : aligné sur le groupe standard (token.refresh + throttle:api-plan).
// #3318 : + api.manager:principal (défense en profondeur, pattern rh.php #3150).
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal'])->group(function (): void {
    Route::get('/sso/status', [SSOController::class, 'status']);
    Route::post('/sso/configure', [SSOController::class, 'configure']);
    Route::delete('/sso/disable', [SSOController::class, 'disable']);
});
