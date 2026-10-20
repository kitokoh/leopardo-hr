<?php

declare(strict_types=1);

/**
 * BC-38 PAIEMENTS — routes du module.
 *
 * Charge depuis routes/api.php a l'interieur du groupe /v1 (jamais de
 * re-prefixe `v1`).
 *
 * Trois surfaces, trois regimes d'authentification distincts :
 *
 *   1. INTERNE (service-a-service) — consommee par les autres BC. Jeton de
 *      service obligatoire (`paiements.service`), idempotence obligatoire.
 *      Jamais de session utilisateur : un BC consommateur n'est pas un humain.
 *
 *   2. WEBHOOKS — sans authentification utilisateur : l'identite de l'appelant
 *      est portee par le TOKEN de l'endpoint, et l'integrite par la signature
 *      HMAC. Fail-closed : sans secret, tout payload est rejete.
 *
 *   3. TENANT — session Sanctum + contexte tenant. Le tenant voit son solde et
 *      demande un retrait ; il ne choisit jamais son fournisseur.
 *
 * La surface Superadmin (types en lecture seule, CRUD instances, journal,
 * reconciliation) est enregistree dans le groupe `platform` de routes/api.php,
 * avec les autres routes plateforme.
 */

use App\Modules\Paiements\Interfaces\Api\V1\Controllers\InternalPaymentController;
use App\Modules\Paiements\Interfaces\Api\V1\Controllers\PaymentWebhookController;
use App\Modules\Paiements\Interfaces\Api\V1\Controllers\TenantPaymentController;
use Illuminate\Support\Facades\Route;

// ── 1. API interne service-a-service ────────────────────────────────────────
Route::middleware(['paiements.service', 'throttle:api'])
    ->prefix('paiements/internal')
    ->group(function (): void {
        Route::post('/credit', [InternalPaymentController::class, 'credit']);
        Route::post('/debit', [InternalPaymentController::class, 'debit']);
        Route::post('/transfer', [InternalPaymentController::class, 'transfer']);
        Route::post('/refund', [InternalPaymentController::class, 'refund']);
    });

// ── 2. Webhooks entrants (routage par token) ────────────────────────────────
Route::middleware(['throttle:webhooks-inbound'])
    ->prefix('paiements/webhooks')
    ->group(function (): void {
        Route::post('/{token}', [PaymentWebhookController::class, 'handle'])
            ->where('token', '[A-Za-z0-9]{16,64}');
    });

// ── 3. Surface Tenant ───────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])
    ->prefix('paiements')
    ->group(function (): void {
        Route::get('/wallet', [TenantPaymentController::class, 'balance']);
        Route::get('/wallet/history', [TenantPaymentController::class, 'history']);
        Route::get('/market', [TenantPaymentController::class, 'market']);
        Route::get('/fees', [TenantPaymentController::class, 'fees']);
        Route::post('/cash-out', [TenantPaymentController::class, 'requestCashOut']);
    });
