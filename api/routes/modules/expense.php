<?php

declare(strict_types=1);

use App\Modules\Expense\Interfaces\Api\V1\Controllers\ExpenseClaimController;
use App\Modules\Expense\Interfaces\Api\V1\ExpenseAccountingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
    Route::get('/expense-claims', [ExpenseClaimController::class, 'index']);
    Route::post('/expense-claims', [ExpenseClaimController::class, 'store']);
    Route::get('/expense-claims/{expenseClaim}', [ExpenseClaimController::class, 'show']);
    // #4933 : mise à jour (brouillon/rejeté) + suppression (brouillon) par le
    // propriétaire — complète le cycle notes de frais.
    Route::put('/expense-claims/{expenseClaim}', [ExpenseClaimController::class, 'update']);
    Route::delete('/expense-claims/{expenseClaim}', [ExpenseClaimController::class, 'destroy']);
    Route::put('/expense-claims/{expenseClaim}/submit', [ExpenseClaimController::class, 'submit']);

    Route::middleware('api.manager')->group(function (): void {
        Route::post('/expense-claims/{expenseClaim}/approve', [ExpenseClaimController::class, 'approve']);
        Route::put('/expense-claims/{expenseClaim}/approve', [ExpenseClaimController::class, 'approve']); // déprécié #4930
        Route::post('/expense-claims/{expenseClaim}/reject', [ExpenseClaimController::class, 'reject']);
        Route::put('/expense-claims/{expenseClaim}/reject', [ExpenseClaimController::class, 'reject']); // déprécié #4930
    });

    // ── Écritures comptables des notes de frais (issue #5235, Phase C) ────
    // RBAC : lecture principal/comptable, régénération réservée au comptable
    // (garde défensive `hasManagerRole('comptable')` dans le contrôleur —
    // miroir du flux paie #5239). Isolation tenant fail-closed (404 cross-tenant).
    Route::middleware('api.manager:principal,comptable')->group(function (): void {
        Route::get('/expense-claims/{expenseClaim}/accounting-entries', [ExpenseAccountingController::class, 'index']);
        Route::post('/expense-claims/{expenseClaim}/accounting-entries/regenerate', [ExpenseAccountingController::class, 'regenerate']);
    });
});
