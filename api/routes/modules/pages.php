<?php

declare(strict_types=1);

/**
 * Routes du BC PAGES — surface TENANT (#8728).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 (jamais de
 * re-préfixe `v1`).
 *
 * Middleware du groupe (convention modules, cf. catalog.php) :
 *   - throttle:api, auth:sanctum, token.refresh, tenant, throttle:api-plan
 *   - api.manager:principal,rh,module:pages → édition réservée aux rôles de
 *     gestion, avec délégation possible par grant de module (#7761).
 *
 * La policy `PagePolicy` reste la seconde barrière : le middleware filtre
 * l'accès au module, la policy décide par ressource. Les deux sont
 * nécessaires — le middleware ne connaît pas la Page visée.
 *
 * Aucune route publique ici : la surface publique est #8730.
 */

use App\Modules\Pages\Interfaces\Api\V1\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal,rh,module:pages'])
    ->prefix('pages')
    ->group(function (): void {
        Route::get('/', [PageController::class, 'index']);
        Route::post('/', [PageController::class, 'store']);

        Route::get('/{page}', [PageController::class, 'show'])->whereNumber('page');
        Route::put('/{page}', [PageController::class, 'update'])->whereNumber('page');

        // Transitions de publication — gestes distincts, routes distinctes.
        Route::post('/{page}/publish', [PageController::class, 'publish'])->whereNumber('page');
        Route::post('/{page}/unpublish', [PageController::class, 'unpublish'])->whereNumber('page');

        // Contenu : remplacement atomique de la liste ordonnée de blocs.
        Route::put('/{page}/blocks', [PageController::class, 'replaceBlocks'])->whereNumber('page');

        // Aperçu privé d'un brouillon (noindex, no-store).
        Route::get('/{page}/preview', [PageController::class, 'preview'])->whereNumber('page');
    });
