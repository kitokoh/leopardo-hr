<?php

/**
 * Routes Marketing — Phase 3.
 *
 * Espace manager marketing/principal : connexion/deconnexion du compte
 * social (agregateur Ayrshare) et gestion des publications (CRUD +
 * planification/publication immediate). Meme empilement middleware que
 * les autres routes tenant (dashboard.php) + restriction manager_role
 * dediee (api.manager:marketing,principal), en plus des policies
 * SocialAccountPolicy/SocialPostPolicy appliquees dans les controleurs.
 */

use App\Modules\Marketing\Interfaces\Api\V1\Controllers\SocialAccountController;
use App\Modules\Marketing\Interfaces\Api\V1\Controllers\SocialPostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'tenant', 'throttle:api-plan', 'api.manager:marketing,principal'])
    ->prefix('marketing')
    ->group(function (): void {
        Route::get('/social-account', [SocialAccountController::class, 'show']);
        Route::post('/social-account/connect', [SocialAccountController::class, 'connect']);
        Route::post('/social-account/disconnect', [SocialAccountController::class, 'disconnect']);

        Route::get('/social-posts', [SocialPostController::class, 'index']);
        Route::post('/social-posts', [SocialPostController::class, 'store']);
        Route::get('/social-posts/{socialPost}', [SocialPostController::class, 'show']);
        Route::patch('/social-posts/{socialPost}', [SocialPostController::class, 'update']);
        Route::delete('/social-posts/{socialPost}', [SocialPostController::class, 'destroy']);
        Route::post('/social-posts/{socialPost}/publish', [SocialPostController::class, 'publish']);
    });
