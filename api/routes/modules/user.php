<?php

/**
 * Routes User — comptes ordinaires (sans entreprise).
 *
 * Permet a un utilisateur lambda de creer un compte, se connecter
 * via email/password ou Google, et gerer son espace personnel.
 */

use App\Modules\Billing\Interfaces\Api\V1\CompanyRequestController;
use App\Core\Auth\Interfaces\Api\V1\UserAuthController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\UserEmployeeLinkController;
use Illuminate\Support\Facades\Route;

// Public (sans auth, throttle strict)
Route::middleware(['throttle:auth-sensitive'])->prefix('user')->group(function (): void {
    Route::post('/register', [UserAuthController::class, 'register']);
    Route::post('/login', [UserAuthController::class, 'login']);
    Route::post('/google-signin', [UserAuthController::class, 'googleSignIn']);
});

// Authentifie (user_api guard)
Route::middleware(['throttle:api', 'auth:user_api'])->prefix('user')->group(function (): void {
    Route::get('/me', [UserAuthController::class, 'me']);
    Route::patch('/profile', [UserAuthController::class, 'updateProfile']);
    Route::get('/personal-onboarding', [UserAuthController::class, 'personalOnboarding']);
    Route::put('/personal-onboarding', [UserAuthController::class, 'updatePersonalOnboarding']);
    Route::post('/change-password', [UserAuthController::class, 'changePassword']);
    Route::post('/logout', [UserAuthController::class, 'logout']);

    // Demandes de creation d'entreprise
    Route::get('/company-requests', [CompanyRequestController::class, 'index']);
    Route::post('/company-requests', [CompanyRequestController::class, 'store']);
    Route::get('/company-requests/{id}', [CompanyRequestController::class, 'show'])->whereNumber('id');

    // Annuaire et demandes d’integration employe
    Route::get('/companies/directory', [UserEmployeeLinkController::class, 'companyDirectory']);
    Route::get('/employee-join-requests', [UserEmployeeLinkController::class, 'myJoinRequests']);
    Route::post('/employee-join-requests', [UserEmployeeLinkController::class, 'requestToJoin']);

    // Liens employe
    Route::get('/employee-links', [UserEmployeeLinkController::class, 'myLinks']);
});

// Manager: lier un utilisateur ordinaire a un employe
// #3244 : groupe api.manager (défense en profondeur — famille #3150) — le
// garde isManager() du contrôleur reste en second rideau.
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager'])->group(function (): void {
    Route::post('/employees/link-user', [UserEmployeeLinkController::class, 'linkByEmail']);
    Route::post('/employee-join-requests/{joinRequest}/approve', [UserEmployeeLinkController::class, 'approveJoinRequest'])
        ->whereNumber('joinRequest');
});
