<?php

/**
 * Routes SSO — SAML 2.0 / OpenID Connect stub.
 * Plan 15 item K2.
 */

use App\Core\Auth\Interfaces\Api\V1\SSOController;
use Illuminate\Support\Facades\Route;

// Public: supported providers list
Route::get('/sso/providers', [SSOController::class, 'providers']);

// Public: SSO callbacks (no auth required — these receive IdP responses)
// QA #3000 : throttles sur les endpoints publics (anti abuse SAMLResponse/redirects)
// + whereUuid sur {companyId} SAML (cohérent avec OIDC).
Route::post('/sso/saml/{companyId}/callback', [SSOController::class, 'samlCallback'])
    ->whereUuid('companyId')->middleware('throttle:api');
Route::get('/sso/oidc/{companyId}/authorize', [SSOController::class, 'oidcAuthorize'])
    ->whereUuid('companyId')->middleware('throttle:api');
Route::get('/sso/oidc/{companyId}/callback', [SSOController::class, 'oidcCallback'])
    ->whereUuid('companyId')->middleware('throttle:api');

// Authenticated: SSO management (manager principal only)
// #2635 : aligné sur le groupe standard (token.refresh + throttle:api-plan).
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal'])->group(function (): void {
    Route::get('/sso/status', [SSOController::class, 'status']);
    Route::post('/sso/configure', [SSOController::class, 'configure']);
    Route::delete('/sso/disable', [SSOController::class, 'disable']);
});
