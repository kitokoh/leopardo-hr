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

use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacyAlertController;
use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacyControlledRegisterController;
use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacyPrescriberController;
use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacyPrescriptionController;
use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacyProductController;
use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacyPurchaseOrderController;
use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacySaleController;
use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacyStockController;
use App\Modules\Pharmacy\Interfaces\Api\V1\Controllers\PharmacySupplierController;
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

        // ── Stock par lots, mouvements, alertes (PHARMA-003, #7800) ─────────
        Route::get('/stock', [PharmacyStockController::class, 'levels']);
        Route::get('/stock/products/{product}/batches', [PharmacyStockController::class, 'batches'])->whereNumber('product');
        Route::get('/stock/movements', [PharmacyStockController::class, 'movements']);
        Route::post('/stock/adjustments', [PharmacyStockController::class, 'storeAdjustment']);
        Route::get('/alerts', [PharmacyAlertController::class, 'index']);

        // ── Fournisseurs et commandes d'achat (PHARMA-004, #7801) ──────────
        Route::get('/suppliers', [PharmacySupplierController::class, 'index']);
        Route::post('/suppliers', [PharmacySupplierController::class, 'store']);
        Route::get('/suppliers/{supplier}', [PharmacySupplierController::class, 'show'])->whereNumber('supplier');
        Route::put('/suppliers/{supplier}', [PharmacySupplierController::class, 'update'])->whereNumber('supplier');
        Route::get('/purchase-orders', [PharmacyPurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [PharmacyPurchaseOrderController::class, 'store']);
        Route::get('/purchase-orders/{purchaseOrder}', [PharmacyPurchaseOrderController::class, 'show'])->whereNumber('purchaseOrder');
        Route::post('/purchase-orders/{purchaseOrder}/place', [PharmacyPurchaseOrderController::class, 'place'])->whereNumber('purchaseOrder');
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PharmacyPurchaseOrderController::class, 'cancel'])->whereNumber('purchaseOrder');
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PharmacyPurchaseOrderController::class, 'receive'])->whereNumber('purchaseOrder');

        // ── Ventes comptoir POS (PHARMA-005, #7802) ───────────────────────
        Route::get('/sales', [PharmacySaleController::class, 'index']);
        Route::post('/sales', [PharmacySaleController::class, 'store']);
        Route::get('/sales/{sale}', [PharmacySaleController::class, 'show'])->whereNumber('sale');
        Route::post('/sales/{sale}/void', [PharmacySaleController::class, 'void'])->whereNumber('sale');

        // ── Ordonnances, prescripteurs, ordonnancier (PHARMA-006, #7803) ────
        Route::get('/prescribers', [PharmacyPrescriberController::class, 'index']);
        Route::post('/prescribers', [PharmacyPrescriberController::class, 'store']);
        Route::get('/prescribers/{prescriber}', [PharmacyPrescriberController::class, 'show'])->whereNumber('prescriber');
        Route::put('/prescribers/{prescriber}', [PharmacyPrescriberController::class, 'update'])->whereNumber('prescriber');
        Route::get('/prescriptions', [PharmacyPrescriptionController::class, 'index']);
        Route::post('/prescriptions', [PharmacyPrescriptionController::class, 'store']);
        Route::get('/prescriptions/{prescription}', [PharmacyPrescriptionController::class, 'show'])->whereNumber('prescription');
        Route::put('/prescriptions/{prescription}', [PharmacyPrescriptionController::class, 'update'])->whereNumber('prescription');
        // Ordonnancier : LECTURE SEULE (dérivé du journal immuable des mouvements).
        Route::get('/controlled-register', [PharmacyControlledRegisterController::class, 'index']);
    });
