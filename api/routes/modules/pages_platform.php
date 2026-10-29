<?php

declare(strict_types=1);

/**
 * Routes du BC PAGES — surface PLATEFORME (#8729).
 *
 * Chargé depuis routes/api.php À L'INTÉRIEUR du groupe `/platform` déjà
 * protégé par `auth:super_admin_api` + `throttle:platform-sensitive` : on ne
 * re-déclare ni l'authentification ni le préfixe.
 *
 * Chaque route porte en plus sa permission fine (`platform.permission`), comme
 * les autres écrans de la console :
 *   - `pages.view`   → recherche, fiche, lecture des métadonnées de pièce
 *   - `pages.manage` → suspension, levée, décision de vérification
 *
 * La séparation lecture/écriture n'est pas décorative : un rôle support doit
 * pouvoir instruire un litige (lire) sans pouvoir suspendre une Page (écrire).
 */

use App\Modules\Pages\Interfaces\Api\V1\Controllers\PlatformPageController;
use Illuminate\Support\Facades\Route;

Route::prefix('pages')->group(function (): void {
    Route::get('/', [PlatformPageController::class, 'index'])
        ->middleware('platform.permission:pages.view');

    Route::get('/{company}/{page}', [PlatformPageController::class, 'show'])
        ->whereNumber('page')
        ->middleware('platform.permission:pages.view');

    Route::post('/{company}/{page}/suspend', [PlatformPageController::class, 'suspend'])
        ->whereNumber('page')
        ->middleware('platform.permission:pages.manage');

    Route::post('/{company}/{page}/unsuspend', [PlatformPageController::class, 'unsuspend'])
        ->whereNumber('page')
        ->middleware('platform.permission:pages.manage');

    Route::post('/{company}/{page}/verification/{requestId}/decide', [PlatformPageController::class, 'decideVerification'])
        ->whereNumber('page')
        ->whereNumber('requestId')
        ->middleware('platform.permission:pages.manage');

    Route::get('/{company}/{page}/evidence/{evidenceId}', [PlatformPageController::class, 'evidence'])
        ->whereNumber('page')
        ->whereNumber('evidenceId')
        ->middleware('platform.permission:pages.view');
});
