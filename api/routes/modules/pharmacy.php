<?php

/**
 * Routes Pharmacy (solution verticale PharmaManager) — PHARMA-001 (#7798).
 *
 * Toutes les routes sont tenant-scoped et soumises au feature flag
 * `pharmacy` : solution inactive → 403 PHARMACY_SOLUTION_INACTIVE
 * (contrôle `assertSolutionActive()` dans chaque contrôleur, fail-closed).
 *
 * Chemins : /pharmacy/... (ids numériques bigint, whereNumber).
 * RBAC : écriture manager (Policies Pharmacy), lecture employé du tenant.
 * PII santé (patients/ordonnances) jamais exposées hors tenant.
 */

use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacyProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])
    ->prefix('pharmacy')
    ->group(function (): void {

        // ── Référentiel produits (PHARMA-002, #7799) ────────────────────────
        Route::get('/products', [PharmacyProductController::class, 'index']);
        Route::post('/products', [PharmacyProductController::class, 'store']);
        Route::get('/products/{product}', [PharmacyProductController::class, 'show'])->whereNumber('product');
        Route::put('/products/{product}', [PharmacyProductController::class, 'update'])->whereNumber('product');
        Route::patch('/products/{product}/archive', [PharmacyProductController::class, 'archive'])->whereNumber('product');
    });
