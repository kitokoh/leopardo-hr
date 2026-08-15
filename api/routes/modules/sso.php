<?php

/**
 * Routes SSO — SAML 2.0 / OpenID Connect stub.
 * Plan 15 item K2.
 */

use App\Core\Auth\Interfaces\Api\V1\SSOController;
use Illuminate\Support\Facades\Route;

// Public: supported providers list
// Issue #3497 : throttles sur les callbacks publics (SAML décode du XML non
// authentifié → abus possible en flood/bruteforce de companyId). #3000 avait
// été fermé à tort sur ce point — rétabli avec un throttle IP dédié.
Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/sso/providers', [SSOController::class, 'providers']);
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
