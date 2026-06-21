<?php

use App\Http\Controllers\Api\V1\PartnerDashboardController;
use App\Http\Controllers\Api\V1\GrowthAdminController;
use Illuminate\Support\Facades\Route;

// Espace Partenaire (Web Client)
// Access via the main dashboard requires the sanctum guard (Employee token).
Route::middleware(['auth:sanctum'])->prefix('partner')->group(function () {
    Route::post('/apply', [PartnerDashboardController::class, 'apply']);
    Route::post('/payout', [PartnerDashboardController::class, 'requestPayout']);
    Route::get('/stats', [PartnerDashboardController::class, 'stats']);
    Route::get('/companies', [PartnerDashboardController::class, 'referredCompanies']);
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
