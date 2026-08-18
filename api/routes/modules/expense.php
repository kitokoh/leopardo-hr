<?php

declare(strict_types=1);

use App\Modules\Expense\Interfaces\Api\V1\Controllers\ExpenseClaimController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan'])->group(function (): void {
    Route::get('/expense-claims', [ExpenseClaimController::class, 'index']);
    Route::post('/expense-claims', [ExpenseClaimController::class, 'store']);
    Route::get('/expense-claims/{expenseClaim}', [ExpenseClaimController::class, 'show']);
    Route::put('/expense-claims/{expenseClaim}/submit', [ExpenseClaimController::class, 'submit']);

    Route::middleware('api.manager')->group(function (): void {
        Route::post('/expense-claims/{expenseClaim}/approve', [ExpenseClaimController::class, 'approve']);
        Route::put('/expense-claims/{expenseClaim}/approve', [ExpenseClaimController::class, 'approve']); // déprécié #4930
        Route::post('/expense-claims/{expenseClaim}/reject', [ExpenseClaimController::class, 'reject']);
        Route::put('/expense-claims/{expenseClaim}/reject', [ExpenseClaimController::class, 'reject']); // déprécié #4930
    });
});
