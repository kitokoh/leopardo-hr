<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\WebAuthController;
use App\Http\Controllers\Web\WebEmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest:web')->group(function (): void {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [WebAuthController::class, 'logout'])
    ->middleware('auth:web')
    ->name('logout');

Route::middleware(['auth:web', 'tenant', 'manager'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/employees/{employee}', [WebEmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{employee}/quick-estimate', [WebEmployeeController::class, 'quickEstimate'])->name('employees.quickEstimate');
    Route::get('/employees/{employee}/receipt', [WebEmployeeController::class, 'receipt'])->name('employees.receipt');
});
