<?php

/**
 * Routes User — comptes ordinaires (sans entreprise).
 *
 * Permet a un utilisateur lambda de creer un compte, se connecter
 * via email/password ou Google, et gerer son espace personnel.
 */

use App\Http\Controllers\Api\V1\CompanyRequestController;
use App\Http\Controllers\Api\V1\UserAuthController;
use App\Http\Controllers\Api\V1\UserEmployeeLinkController;
use Illuminate\Support\Facades\Route;

// Public (sans auth, throttle strict)
Route::middleware(['throttle:10,1'])->prefix('user')->group(function (): void {
    Route::post('/register', [UserAuthController::class, 'register']);
    Route::post('/login', [UserAuthController::class, 'login']);
    Route::post('/google-signin', [UserAuthController::class, 'googleSignIn']);
});

// Authentifie (user_api guard)
Route::middleware(['throttle:api', 'auth:user_api'])->prefix('user')->group(function (): void {
    Route::get('/me', [UserAuthController::class, 'me']);
    Route::patch('/profile', [UserAuthController::class, 'updateProfile']);
    Route::post('/change-password', [UserAuthController::class, 'changePassword']);
    Route::post('/logout', [UserAuthController::class, 'logout']);

    // Demandes de creation d'entreprise
    Route::get('/company-requests', [CompanyRequestController::class, 'index']);
    Route::post('/company-requests', [CompanyRequestController::class, 'store']);
    Route::get('/company-requests/{id}', [CompanyRequestController::class, 'show'])->whereNumber('id');

    // Liens employe
    Route::get('/employee-links', [UserEmployeeLinkController::class, 'myLinks']);
});

// Manager: lier un utilisateur ordinaire a un employe
Route::middleware(['throttle:api', 'auth:sanctum', 'tenant'])->group(function (): void {
    Route::post('/employees/link-user', [UserEmployeeLinkController::class, 'linkByEmail']);
});
