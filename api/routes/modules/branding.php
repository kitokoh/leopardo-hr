<?php

declare(strict_types=1);

/**
 * Routes du BC BRANDING — surface TENANT (BC-41, #9578).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 (jamais de
 * re-préfixe `v1`, règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. pages.php / retail.php) :
 *   - throttle:api        → limite globale de l'API
 *   - auth:sanctum        → authentification (Sanctum)
 *   - token.refresh       → auto-refresh du token
 *   - tenant              → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan   → limite selon le plan tarifaire
 *   - api.manager:principal,rh,module:pages → édition réservée aux rôles de
 *     gestion. On réutilise le grant `module:pages` : une enseigne publique
 *     (Brand) et une Page sont deux facettes de la même surface publique, et
 *     le droit de les administrer est le même geste produit. Créer un grant
 *     `module:branding` séparé obligerait à re-provisionner tous les tenants
 *     existants pour une capacité qu'ils ont déjà.
 *
 * Le scope global `BelongsToCompany` reste la seconde barrière : une Brand d'un
 * autre tenant est invisible, et un ID étranger renvoie 404.
 *
 * Aucune route publique ici : la surface publique est branding_public.php.
 */

use App\Modules\Branding\Interfaces\Api\V1\Controllers\BrandController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'api.manager:principal,rh,module:pages'])
    ->prefix('brands')
    ->group(function (): void {
        Route::get('/', [BrandController::class, 'index']);
        Route::post('/', [BrandController::class, 'store']);

        Route::get('/{brand}', [BrandController::class, 'show'])->whereNumber('brand');
        Route::patch('/{brand}', [BrandController::class, 'update'])->whereNumber('brand');

        // Transitions — gestes distincts, routes distinctes (convention Pages).
        Route::post('/{brand}/publish', [BrandController::class, 'publish'])->whereNumber('brand');
        Route::post('/{brand}/unpublish', [BrandController::class, 'unpublish'])->whereNumber('brand');

        // Modération plateforme : réservée aux mêmes rôles, la policy reste la
        // seconde barrière pour l'audit.
        Route::post('/{brand}/suspend', [BrandController::class, 'suspend'])->whereNumber('brand');
        Route::post('/{brand}/unsuspend', [BrandController::class, 'unsuspend'])->whereNumber('brand');
    });
