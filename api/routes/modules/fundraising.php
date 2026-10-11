<?php

declare(strict_types=1);

/**
 * Routes du module Fundraising (verticale cagnottes solidaires — spec
 * docs/specifications/SOLUTION_FUNDRAISING.md §5).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 (jamais de
 * re-préfixe `v1`).
 *
 * PUBLIQUE (sans compte) — groupe isolé `throttle:shop-public` (aucun
 * middleware tenant/utilisateur, pattern BC-27 V-PUBLIC-API) :
 * fiche cagnotte, mur des soutiens, initiation de contribution, polling.
 * Résolution tenant par annuaire public + gardes fail-closed (BOS-050) —
 * le webhook provider est enregistré dans routes/api.php, groupe
 * `throttle:webhooks-inbound` (PA2-API-005).
 *
 * PRIVÉE (gestion tenant) — middlewares du groupe :
 *   - throttle:api, auth:sanctum, token.refresh, tenant, throttle:api-plan
 *     (hérités des modules, cf. showcase.php) ;
 *   - module.fundraising → feature flag companies.features.fundraising
 *     (fail-closed : tenant sans le flag → 403) ;
 *   - api.manager:principal,rh → édition réservée au responsable du tenant
 *     (cohérent avec FundraiserPolicy / FundraisingPayoutPolicy).
 */

use App\Modules\Fundraising\Interfaces\Api\V1\Controllers\FundraiserController;
use App\Modules\Fundraising\Interfaces\Api\V1\Controllers\FundraiserPublicController;
use App\Modules\Fundraising\Interfaces\Api\V1\Controllers\FundraisingPayoutController;
use Illuminate\Support\Facades\Route;

// ── Publique (isolée, sans auth ni tenant) ──────────────────────────────────
Route::middleware(['throttle:shop-public'])
    ->prefix('public')
    ->group(function (): void {
        Route::get('/fundraisers/{slug}', [FundraiserPublicController::class, 'show'])
            ->where('slug', '[A-Za-z0-9\-_]{1,160}');
        Route::get('/fundraisers/{slug}/supporters', [FundraiserPublicController::class, 'supporters'])
            ->where('slug', '[A-Za-z0-9\-_]{1,160}');
        Route::post('/fundraisers/{slug}/contribute', [FundraiserPublicController::class, 'contribute'])
            ->where('slug', '[A-Za-z0-9\-_]{1,160}');
        Route::get('/contributions/{reference}', [FundraiserPublicController::class, 'contributionStatus'])
            ->where('reference', 'FC-[A-Z0-9]{1,32}');
    });

// ── Privée (gestion tenant) ─────────────────────────────────────────────────
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.fundraising', 'api.manager:principal,rh'])
    ->prefix('fundraising')
    ->group(function (): void {
        // Cagnottes
        Route::get('/fundraisers', [FundraiserController::class, 'index']);
        Route::post('/fundraisers', [FundraiserController::class, 'store']);
        Route::get('/fundraisers/{fundraiser}', [FundraiserController::class, 'show'])->whereNumber('fundraiser');
        Route::put('/fundraisers/{fundraiser}', [FundraiserController::class, 'update'])->whereNumber('fundraiser');
        Route::post('/fundraisers/{fundraiser}/publish', [FundraiserController::class, 'publish'])->whereNumber('fundraiser');
        Route::post('/fundraisers/{fundraiser}/pause', [FundraiserController::class, 'pause'])->whereNumber('fundraiser');
        Route::post('/fundraisers/{fundraiser}/close', [FundraiserController::class, 'close'])->whereNumber('fundraiser');
        Route::post('/fundraisers/{fundraiser}/cancel', [FundraiserController::class, 'cancel'])->whereNumber('fundraiser');

        // Contributions
        Route::get('/fundraisers/{fundraiser}/contributions', [FundraiserController::class, 'contributions'])->whereNumber('fundraiser');
        Route::post('/contributions/{contribution}/confirm', [FundraiserController::class, 'confirmContribution'])->whereNumber('contribution');

        // Reversements
        Route::get('/fundraisers/{fundraiser}/payouts', [FundraisingPayoutController::class, 'index'])->whereNumber('fundraiser');
        Route::post('/fundraisers/{fundraiser}/payouts', [FundraisingPayoutController::class, 'store'])->whereNumber('fundraiser');
        Route::post('/payouts/{payout}/process', [FundraisingPayoutController::class, 'process'])->whereNumber('payout');
        Route::post('/payouts/{payout}/mark-paid', [FundraisingPayoutController::class, 'markPaid'])->whereNumber('payout');
        Route::post('/payouts/{payout}/fail', [FundraisingPayoutController::class, 'fail'])->whereNumber('payout');
        Route::post('/payouts/{payout}/cancel', [FundraisingPayoutController::class, 'cancel'])->whereNumber('payout');
    });
