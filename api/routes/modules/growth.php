<?php

use App\Modules\Growth\Interfaces\Api\V1\Controllers\GrowthAdminController;
use App\Modules\Growth\Interfaces\Api\V1\Controllers\PartnerDashboardController;
use Illuminate\Support\Facades\Route;

// Espace Partenaire (Web Client)
// Access via the main dashboard requires the sanctum guard (Employee token).
// Audit expert 2026-08-15 (issue #2622) : le middleware `tenant` manquait —
// les POST /apply et /payout écrivaient en cross-tenant (le scope
// BelongsToCompany était inerte sans search_path tenant).
Route::prefix('growth')->group(function () {
    Route::middleware(['auth:sanctum', 'tenant'])->prefix('partner')->group(function () {
        Route::post('/apply', [PartnerDashboardController::class, 'apply']);
        Route::post('/payout', [PartnerDashboardController::class, 'requestPayout']);
        Route::get('/dashboard', [PartnerDashboardController::class, 'dashboard']);
        Route::get('/stats', [PartnerDashboardController::class, 'stats']);
        Route::get('/companies', [PartnerDashboardController::class, 'referredCompanies']);
    });
});

// Espace Administration (Super Admin)
Route::middleware(['auth:super_admin_api'])->prefix('platform/growth')->group(function () {
    Route::get('/partners', [GrowthAdminController::class, 'partners']);
    Route::patch('/partners/{partner}/rate', [GrowthAdminController::class, 'updateRate']);
    Route::patch('/partners/{partner}/application', [GrowthAdminController::class, 'updateApplicationStatus']);
    Route::get('/payouts', [GrowthAdminController::class, 'payouts']);
    Route::patch('/payouts/{payout}', [GrowthAdminController::class, 'updatePayoutStatus']);
    Route::get('/history', [GrowthAdminController::class, 'history']);
});
