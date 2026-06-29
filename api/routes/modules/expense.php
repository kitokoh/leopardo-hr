<?php

declare(strict_types=1);

use App\Modules\Expense\Interfaces\Api\V1\Controllers\ExpenseClaimController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Expense Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1/expense-claims')->group(function () {
    Route::get('/',                          [ExpenseClaimController::class, 'index']);
    Route::post('/',                         [ExpenseClaimController::class, 'store']);
    Route::get('/{expenseClaim}',            [ExpenseClaimController::class, 'show']);
    Route::post('/{expenseClaim}/submit',    [ExpenseClaimController::class, 'submit']);
    Route::put('/{expenseClaim}/approve',    [ExpenseClaimController::class, 'approve']);
    Route::post('/{expenseClaim}/approve',   [ExpenseClaimController::class, 'approve']);
    Route::post('/{expenseClaim}/reject',    [ExpenseClaimController::class, 'reject']);
});
