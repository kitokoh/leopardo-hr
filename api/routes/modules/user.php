<?php

/**
 * Routes User — comptes ordinaires (sans entreprise).
 *
 * Permet a un utilisateur lambda de creer un compte, se connecter
 * via email/password ou Google, et gerer son espace personnel.
 */

use App\Core\Auth\Interfaces\Api\V1\Controllers\UserAuthController;
use App\Modules\Billing\Interfaces\Api\V1\Controllers\CompanyRequestController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\CompanyIntegrationRequestController;
use App\Modules\HR\Interfaces\Api\V1\Controllers\UserEmployeeLinkController;
use App\Modules\Notification\Interfaces\Api\V1\Controllers\PublicConversationController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PublicActivityController;
use App\Modules\Platform\Interfaces\Api\V1\Controllers\PublicNotificationController;
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

// #8760 [BOS-CHAT] — Messagerie de la passerelle publique, côté CLIENT.
//
// Groupe DÉDIÉ, hors du préfixe `user` : l'URL publique est
// `/api/v1/public/conversations`, pas `/api/v1/user/public/conversations`.
//
// Le client est un `users.id` (guard `user_api`), pas un employé : il n'y a
// donc AUCUN middleware `tenant` ici. Un client converse avec plusieurs
// entreprises, et le tenant est résolu côté serveur depuis le `target_slug`
// du profil public — jamais depuis un identifiant fourni par le client.
//
// Throttle `shop-public` : ce sont des écritures déclenchées par un visiteur,
// elles ne doivent pas pouvoir saturer un professionnel.
Route::middleware(['throttle:shop-public', 'auth:user_api'])->prefix('public/conversations')->group(function (): void {
    Route::get('/', [PublicConversationController::class, 'index']);
    Route::post('/', [PublicConversationController::class, 'store']);
    Route::get('/{companyId}/{thread}', [PublicConversationController::class, 'show'])
        ->whereNumber('thread');
    Route::get('/{companyId}/{thread}/messages', [PublicConversationController::class, 'messages'])
        ->whereNumber('thread');
    Route::post('/{companyId}/{thread}/messages', [PublicConversationController::class, 'storeMessage'])
        ->whereNumber('thread');
});

// COULOIR 16 — M3 (#9105) · Activités, favoris et historique de recherche.
//
// Même régime que `/public/conversations` : le client est un `users.id`
// (guard `user_api`), il n'y a AUCUN middleware `tenant`. Un client agit sur
// plusieurs entreprises, et le tenant d'une activité est résolu côté serveur
// depuis la référence — jamais depuis un identifiant fourni par le client.
//
// Throttle `shop-public` : ce sont des écritures déclenchées par un visiteur.
Route::middleware(['throttle:shop-public', 'auth:user_api'])->prefix('user')->group(function (): void {
    // Activités : l'index ne contient que des références, chaque ligne est
    // revalidée dans son contexte tenant avant d'être renvoyée.
    Route::get('/activities', [PublicActivityController::class, 'index']);

    // Favoris : `(target_type, target_slug)`, jamais de `company_id` client.
    Route::get('/favorites', [PublicActivityController::class, 'favorites']);
    Route::post('/favorites', [PublicActivityController::class, 'storeFavorite']);
    Route::delete('/favorites/{type}/{slug}', [PublicActivityController::class, 'destroyFavorite'])
        ->where('type', '[a-z_]{1,40}')
        ->where('slug', '[A-Za-z0-9_-]{1,160}');

    // Historique de recherche : local d'abord, synchronisé si compte.
    Route::get('/search-history', [PublicActivityController::class, 'searchHistory']);
    Route::post('/search-history', [PublicActivityController::class, 'storeSearchHistory']);
    Route::delete('/search-history', [PublicActivityController::class, 'clearSearchHistory']);

    // COULOIR 16 — M3-bis (#9142) · Première habitude quotidienne.
    //
    // Boîte de notifications IN-APP du client : réponse d'un commerçant et
    // changement de statut d'une commande/réservation. Le push (FCM/APNs)
    // arrive en M2 avec `public_device_tokens` ; ici tout est in-app, donc
    // aucune porte n'est franchie.
    //
    // `read-all` est déclarée AVANT `{notification}/read` : sans cela, le
    // routeur tenterait de lier « read-all » à un entier et renverrait 404.
    Route::get('/notifications', [PublicNotificationController::class, 'index']);
    Route::post('/notifications/read-all', [PublicNotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [PublicNotificationController::class, 'markRead'])
        ->whereNumber('notification');
});

// Manager: lier un utilisateur ordinaire a un employe
// #3244 : groupe api.manager (défense en profondeur — famille #3150) — le
// garde isManager() du contrôleur reste en second rideau.
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager'])->group(function (): void {
    Route::post('/employees/link-user', [UserEmployeeLinkController::class, 'linkByEmail']);

    // #5540 — Gestion des demandes d'intégration (côté manager/RH)
    Route::get('/company-integration-requests/manage', [CompanyIntegrationRequestController::class, 'managerIndex']);
    Route::post('/company-integration-requests/{id}/accept', [CompanyIntegrationRequestController::class, 'accept'])->whereNumber('id');
    Route::post('/company-integration-requests/{id}/reject', [CompanyIntegrationRequestController::class, 'reject'])->whereNumber('id');
});
