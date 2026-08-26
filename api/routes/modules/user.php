<?php

/**
 * Routes User — comptes ordinaires (sans entreprise).
 *
 * Permet a un utilisateur lambda de creer un compte, se connecter
 * via email/password ou Google, et gerer son espace personnel.
 */

use App\Modules\Billing\Interfaces\Api\V1\CompanyRequestController;
use App\Core\Auth\Interfaces\Api\V1\UserAuthController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\CompanyIntegrationRequestController;
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
    Route::post('/change-password', [UserAuthController::class, 'changePassword']);
    Route::post('/logout', [UserAuthController::class, 'logout']);

    // #5540 — Statuts personnels cumulables (étudiant/employé/entrepreneur/chercheur d'emploi)
    Route::patch('/personal-statuses', [UserAuthController::class, 'updatePersonalStatuses']);

    // #5540 — Recherche d'entreprises (pour demandes d'intégration)
    Route::get('/companies/search', [UserAuthController::class, 'searchCompanies']);

    // Demandes de création d'entreprise (historique)
    Route::get('/company-requests', [CompanyRequestController::class, 'index']);
    Route::post('/company-requests', [CompanyRequestController::class, 'store']);
    Route::get('/company-requests/{id}', [CompanyRequestController::class, 'show'])->whereNumber('id');

    // #5540 — Demandes d'intégration (rejoindre une entreprise existante)
    Route::get('/company-integration-requests', [CompanyIntegrationRequestController::class, 'index']);
    Route::post('/company-integration-requests', [CompanyIntegrationRequestController::class, 'store']);

    // Liens employé
    Route::get('/employee-links', [UserEmployeeLinkController::class, 'myLinks']);
});

// Manager: lier un utilisateur ordinaire a un employe
// #3244 : groupe api.manager (défense en profondeur — famille #3150) — le
// garde isManager() du contrôleur reste en second rideau.
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager'])->group(function (): void {
    Route::post('/employees/link-user', [UserEmployeeLinkController::class, 'linkByEmail']);

    // #5540 — Gestion des demandes d'intégration (côté manager/RH)
    Route::get('/company-integration-requests', [CompanyIntegrationRequestController::class, 'managerIndex']);
    Route::post('/company-integration-requests/{id}/accept', [CompanyIntegrationRequestController::class, 'accept'])->whereNumber('id');
    Route::post('/company-integration-requests/{id}/reject', [CompanyIntegrationRequestController::class, 'reject'])->whereNumber('id');
});
