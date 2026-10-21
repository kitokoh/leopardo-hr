<?php

/**
 * P2-01 (#8802) — routes PUBLIQUES du commerce international / B2B (BC-36 TRADE).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe `/v1` (juste après
 * `market_public.php`) — ne JAMAIS re-préfixer `v1` (règle AGENTS.md).
 * URL réelle : `GET /api/v1/public/trade/companies/{slug}`.
 *
 * Groupe isolé `throttle:shop-public` (pattern BC-27/BC-28) : PAS d'auth
 * Sanctum ni de middleware tenant. Le périmètre opt-in (is_public, slug public
 * unique global, société non suspendue/expirée, feature `trade`) est appliqué
 * par `TradeProfileResolver`, et la lecture du profil revalide `is_public`.
 *
 * 404 fail-closed et UNIFORME : un slug inexistant, un profil dépublié et une
 * société suspendue produisent la même réponse, ce qui empêche l'énumération
 * des professionnels inscrits.
 */

use App\Modules\Trade\Interfaces\Api\V1\Controllers\TradePublicProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:shop-public'])
    ->prefix('public/trade')
    ->group(function (): void {
        Route::get('/companies/{slug}', [TradePublicProfileController::class, 'show'])
            ->where('slug', '[a-z0-9][a-z0-9\-]{0,159}')
            ->name('trade.public.company.show');
    });
